<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\DexChartSeries;
use App\Services\MarketData\DTOs\DexHolderData;
use App\Services\MarketData\DTOs\DexPairData;
use App\Services\MarketData\DTOs\DexPairDetailData;
use App\Services\MarketData\DTOs\DexTokenDetailData;
use App\Services\MarketData\DTOs\DexTradeData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeckoTerminalProvider implements DexDataProvider
{
    /**
     * Map stored chart series to one GT OHLCV request; close prices become line points.
     *
     * @var array<string, array{timeframe: string, aggregate: int, limit: int}>
     */
    private const OHLCV_FETCH = [
        DexChartSeries::SERIES_INTRADAY => ['timeframe' => 'minute', 'aggregate' => 5, 'limit' => 288],
        DexChartSeries::SERIES_SHORT => ['timeframe' => 'hour', 'aggregate' => 1, 'limit' => 168],
        DexChartSeries::SERIES_DAILY => ['timeframe' => 'day', 'aggregate' => 1, 'limit' => 365],
    ];

    public function name(): string
    {
        return 'geckoterminal';
    }

    public function fetchTrendingPools(int $page = 1): Collection
    {
        return $this->fetchPools('/networks/trending_pools', $page, trending: true);
    }

    public function fetchNewPools(int $page = 1): Collection
    {
        return $this->fetchPools('/networks/new_pools', $page, trending: false);
    }

    public function fetchNetworkPools(string $network, int $page = 1): Collection
    {
        throw_unless(is_string($network) && $network !== '', new RuntimeException('Network id is required.'));

        return $this->fetchPools('/networks/' . rawurlencode($network) . '/pools', $page, trending: false);
    }

    public function fetchPoolDetail(string $network, string $poolAddress): DexPairDetailData
    {
        throw_unless($network !== '' && $poolAddress !== '', new RuntimeException('Network and pool address are required.'));

        $path = '/networks/' . rawurlencode($network) . '/pools/' . rawurlencode($poolAddress);
        $response = $this->client()->get($path, [
            'include' => 'base_token,quote_token,dex',
        ]);

        throw_unless($response->successful(), new RuntimeException(
            'GeckoTerminal ' . $path . ' failed: ' . $response->status() . ' ' . $response->body()
        ));

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $row = is_array($payload['data'] ?? null) ? $payload['data'] : null;
        throw_unless(is_array($row), new RuntimeException('GeckoTerminal pool detail returned no data.'));

        $included = $this->indexIncluded(is_array($payload['included'] ?? null) ? $payload['included'] : []);
        $pair = $this->mapPool($row, $included, trending: false, rank: 1);
        throw_unless($pair instanceof DexPairData, new RuntimeException('GeckoTerminal pool detail could not be mapped.'));

        $trades = $this->fetchPoolTrades($network, $poolAddress);
        $charts = $this->fetchPoolCharts($network, $poolAddress);

        return new DexPairDetailData($pair, $trades, $charts);
    }

    public function fetchTokenDetail(string $network, string $tokenAddress): DexTokenDetailData
    {
        throw_unless($network !== '' && $tokenAddress !== '', new RuntimeException('Network and token address are required.'));

        $path = '/networks/' . rawurlencode($network) . '/tokens/' . rawurlencode($tokenAddress);
        $response = $this->client()->get($path, [
            'include' => 'top_pools',
        ]);

        throw_unless($response->successful(), new RuntimeException(
            'GeckoTerminal ' . $path . ' failed: ' . $response->status() . ' ' . $response->body()
        ));

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $row = is_array($payload['data'] ?? null) ? $payload['data'] : null;
        throw_unless(is_array($row), new RuntimeException('GeckoTerminal token detail returned no data.'));

        $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];
        $included = $this->indexIncluded(is_array($payload['included'] ?? null) ? $payload['included'] : []);

        $symbol = data_get($attributes, 'symbol');
        $symbol = is_string($symbol) && $symbol !== '' ? Str::upper($symbol) : 'TOKEN';

        $name = data_get($attributes, 'name');
        $name = is_string($name) && $name !== '' ? $name : null;

        $address = data_get($attributes, 'address');
        $address = is_string($address) && $address !== '' ? $address : $tokenAddress;

        $coingeckoId = data_get($attributes, 'coingecko_coin_id');
        $coingeckoId = is_string($coingeckoId) && $coingeckoId !== '' ? $coingeckoId : null;

        $pools = $this->fetchTokenPools($network, $tokenAddress);
        $trades = $this->fetchTokenTrades($network, $tokenAddress);
        $charts = $this->fetchTokenCharts($network, $tokenAddress, $pools);
        [$holders, $holdersUnavailable] = $this->fetchTokenHolders($network, $tokenAddress);

        $holdersCount = data_get($attributes, 'holders.count')
            ?? data_get($attributes, 'holders_count');

        return new DexTokenDetailData(
            networkId: $network,
            address: $address,
            symbol: $symbol,
            name: $name,
            coingeckoCoinId: $coingeckoId,
            price: $this->floatOrNull($attributes['price_usd'] ?? null),
            percentChange24h: $this->floatOrNull(data_get($attributes, 'price_change_percentage.h24')),
            fdvUsd: $this->floatOrNull($attributes['fdv_usd'] ?? null),
            marketCapUsd: $this->floatOrNull($attributes['market_cap_usd'] ?? null),
            liquidityUsd: $this->floatOrNull($attributes['total_reserve_in_usd'] ?? null)
                ?? $this->sumPoolLiquidity($pools),
            volume24h: $this->floatOrNull(data_get($attributes, 'volume_usd.h24'))
                ?? $this->sumPoolVolume($pools),
            holdersCount: is_numeric($holdersCount) ? (int) $holdersCount : null,
            pools: $pools,
            trades: $trades,
            holders: $holders,
            chartSeries: $charts,
            holdersUnavailable: $holdersUnavailable,
        );
    }

    /**
     * @return Collection<int, DexPairData>
     */
    private function fetchPools(string $path, int $page, bool $trending): Collection
    {
        $response = $this->client()->get($path, [
            'page' => max(1, $page),
            'include' => 'base_token,quote_token,dex',
        ]);

        throw_unless($response->successful(), new RuntimeException(
            'GeckoTerminal ' . $path . ' failed: ' . $response->status() . ' ' . $response->body()
        ));

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $included = $this->indexIncluded(is_array($payload['included'] ?? null) ? $payload['included'] : []);
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return collect($rows)
            ->values()
            ->map(function (mixed $row, int $index) use ($included, $trending): ?DexPairData {
                if (! is_array($row)) {
                    return null;
                }

                return $this->mapPool($row, $included, $trending, $index + 1);
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<DexPairData>
     */
    private function fetchTokenPools(string $network, string $tokenAddress): array
    {
        $path = '/networks/' . rawurlencode($network) . '/tokens/' . rawurlencode($tokenAddress) . '/pools';
        $response = $this->client()->get($path, [
            'page' => 1,
            'include' => 'base_token,quote_token,dex',
        ]);

        if (! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $included = $this->indexIncluded(is_array($payload['included'] ?? null) ? $payload['included'] : []);
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return collect($rows)
            ->values()
            ->map(function (mixed $row, int $index) use ($included): ?DexPairData {
                if (! is_array($row)) {
                    return null;
                }

                return $this->mapPool($row, $included, trending: false, rank: $index + 1);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<DexTradeData>
     */
    private function fetchPoolTrades(string $network, string $poolAddress): array
    {
        $path = '/networks/' . rawurlencode($network) . '/pools/' . rawurlencode($poolAddress) . '/trades';

        return $this->mapTradesResponse($this->client()->get($path));
    }

    /**
     * @return list<DexTradeData>
     */
    private function fetchTokenTrades(string $network, string $tokenAddress): array
    {
        $path = '/networks/' . rawurlencode($network) . '/tokens/' . rawurlencode($tokenAddress) . '/trades';

        return $this->mapTradesResponse($this->client()->get($path));
    }

    /**
     * @return array{0: list<DexHolderData>, 1: bool}
     */
    private function fetchTokenHolders(string $network, string $tokenAddress): array
    {
        if (! $this->hasApiKey()) {
            return [[], true];
        }

        $path = '/networks/' . rawurlencode($network) . '/tokens/' . rawurlencode($tokenAddress) . '/top_holders';
        $response = $this->client()->get($path, ['holders' => 20]);

        if (in_array($response->status(), [401, 403, 404], true)) {
            return [[], true];
        }

        if (! $response->successful()) {
            return [[], true];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $attributes = data_get($payload, 'data.attributes');
        $holders = is_array($attributes) && is_array($attributes['holders'] ?? null)
            ? $attributes['holders']
            : [];

        $mapped = [];
        foreach ($holders as $holder) {
            if (! is_array($holder)) {
                continue;
            }

            $address = $holder['address'] ?? null;
            if (! is_string($address) || $address === '') {
                continue;
            }

            $rank = $holder['rank'] ?? null;
            $label = $holder['label'] ?? null;
            $amount = $holder['amount'] ?? null;
            $explorer = $holder['explorer_url'] ?? null;

            $mapped[] = new DexHolderData(
                rank: is_numeric($rank) ? (int) $rank : (count($mapped) + 1),
                address: $address,
                label: is_string($label) && $label !== '' ? $label : null,
                amount: is_string($amount) || is_numeric($amount) ? (string) $amount : null,
                percentage: $this->floatOrNull($holder['percentage'] ?? null),
                valueUsd: $this->floatOrNull($holder['value'] ?? null),
                explorerUrl: is_string($explorer) && $explorer !== '' ? $explorer : null,
            );
        }

        return [$mapped, false];
    }

    /**
     * @return array<string, list<array{0: int, 1: float}>>
     */
    private function fetchPoolCharts(string $network, string $poolAddress): array
    {
        $series = [];

        foreach (self::OHLCV_FETCH as $key => $meta) {
            $series[$key] = $this->fetchOhlcvClosePoints(
                '/networks/' . rawurlencode($network) . '/pools/' . rawurlencode($poolAddress) . '/ohlcv/' . $meta['timeframe'],
                $meta['aggregate'],
                $meta['limit'],
            );
        }

        return $series;
    }

    /**
     * @param  list<DexPairData>  $pools
     * @return array<string, list<array{0: int, 1: float}>>
     */
    private function fetchTokenCharts(string $network, string $tokenAddress, array $pools): array
    {
        $pool = $pools[0] ?? null;
        if ($pool instanceof DexPairData && filled($pool->contractAddress) && filled($pool->networkId)) {
            return $this->fetchPoolCharts($pool->networkId, $pool->contractAddress);
        }

        // Token OHLCV when available; fall back to empty series.
        $series = [];
        foreach (self::OHLCV_FETCH as $key => $meta) {
            $series[$key] = $this->fetchOhlcvClosePoints(
                '/networks/' . rawurlencode($network) . '/tokens/' . rawurlencode($tokenAddress) . '/ohlcv/' . $meta['timeframe'],
                $meta['aggregate'],
                $meta['limit'],
                allowFailure: true,
            );
        }

        return $series;
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    private function fetchOhlcvClosePoints(string $path, int $aggregate, int $limit, bool $allowFailure = false): array
    {
        $response = $this->client()->get($path, [
            'aggregate' => $aggregate,
            'limit' => $limit,
            'currency' => 'usd',
        ]);

        if (! $response->successful()) {
            if ($allowFailure) {
                return [];
            }

            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $list = data_get($payload, 'data.attributes.ohlcv_list');
        if (! is_array($list)) {
            return [];
        }

        $points = [];
        foreach ($list as $candle) {
            if (! is_array($candle) || count($candle) < 5) {
                continue;
            }

            $ts = $candle[0] ?? null;
            $close = $candle[4] ?? null;
            if (! is_numeric($ts) || ! is_numeric($close)) {
                continue;
            }

            $points[] = [(int) $ts * 1000, (float) $close];
        }

        usort($points, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $points;
    }

    /**
     * @return list<DexTradeData>
     */
    private function mapTradesResponse(Response $response): array
    {
        if (! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $trades = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];
            $txHash = $attributes['tx_hash'] ?? null;
            $kind = $attributes['kind'] ?? null;
            $trader = $attributes['tx_from_address'] ?? null;
            $fromAmount = $attributes['from_token_amount'] ?? null;
            $toAmount = $attributes['to_token_amount'] ?? null;
            $tradedAt = $attributes['block_timestamp'] ?? null;

            $trades[] = new DexTradeData(
                txHash: is_string($txHash) && $txHash !== '' ? $txHash : null,
                kind: is_string($kind) && $kind !== '' ? $kind : null,
                priceUsd: $this->floatOrNull($attributes['price_to_in_usd'] ?? $attributes['price_from_in_usd'] ?? null),
                volumeUsd: $this->floatOrNull($attributes['volume_in_usd'] ?? null),
                fromTokenAmount: is_string($fromAmount) || is_numeric($fromAmount) ? (string) $fromAmount : null,
                toTokenAmount: is_string($toAmount) || is_numeric($toAmount) ? (string) $toAmount : null,
                traderAddress: is_string($trader) && $trader !== '' ? $trader : null,
                tradedAt: is_string($tradedAt) && $tradedAt !== '' ? $tradedAt : null,
            );
        }

        return $trades;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $included
     */
    private function mapPool(array $row, array $included, bool $trending, int $rank): ?DexPairData
    {
        $externalId = isset($row['id']) && is_string($row['id']) ? $row['id'] : '';
        if ($externalId === '') {
            return null;
        }

        $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];
        $relationships = is_array($row['relationships'] ?? null) ? $row['relationships'] : [];

        $baseToken = $this->related($relationships, $included, 'base_token');
        $quoteToken = $this->related($relationships, $included, 'quote_token');
        $dex = $this->related($relationships, $included, 'dex');

        $networkId = data_get($relationships, 'network.data.id');
        $networkId = is_string($networkId) && $networkId !== '' ? $networkId : $this->networkIdFromExternalId($externalId);

        [$baseSymbol, $quoteSymbol] = $this->symbols($attributes, $baseToken, $quoteToken);
        if ($baseSymbol === '' || $quoteSymbol === '') {
            return null;
        }

        $pair = $baseSymbol . '/' . $quoteSymbol;
        $dexName = $this->dexName($dex, data_get($relationships, 'dex.data.id'));
        $chain = $this->chainLabel($networkId ?? '');
        $coingeckoId = data_get($baseToken, 'attributes.coingecko_coin_id');
        $coingeckoId = is_string($coingeckoId) && $coingeckoId !== '' ? $coingeckoId : null;

        $baseName = data_get($baseToken, 'attributes.name');
        $baseName = is_string($baseName) && $baseName !== '' ? $baseName : null;

        $buys = data_get($attributes, 'transactions.h24.buys');
        $sells = data_get($attributes, 'transactions.h24.sells');
        $buysInt = is_numeric($buys) ? (int) $buys : null;
        $sellsInt = is_numeric($sells) ? (int) $sells : null;
        $txns = ($buysInt ?? 0) + ($sellsInt ?? 0);

        $createdAt = $attributes['pool_created_at'] ?? null;
        $pairedAt = is_string($createdAt) && $createdAt !== ''
            ? Carbon::parse($createdAt)
            : null;

        $address = $attributes['address'] ?? null;
        $baseTokenAddress = data_get($baseToken, 'attributes.address');
        $quoteTokenAddress = data_get($quoteToken, 'attributes.address');

        return new DexPairData(
            externalId: $externalId,
            pair: $pair,
            baseSymbol: $baseSymbol,
            quoteSymbol: $quoteSymbol,
            dex: $dexName,
            chain: $chain,
            networkId: $networkId,
            contractAddress: is_string($address) && $address !== '' ? $address : null,
            baseTokenAddress: is_string($baseTokenAddress) && $baseTokenAddress !== '' ? $baseTokenAddress : null,
            quoteTokenAddress: is_string($quoteTokenAddress) && $quoteTokenAddress !== '' ? $quoteTokenAddress : null,
            baseTokenName: $baseName,
            coingeckoCoinId: $coingeckoId,
            auditStatus: $coingeckoId !== null ? 'partial' : 'unverified',
            price: $this->floatOrNull($attributes['base_token_price_usd'] ?? null),
            percentChange24h: $this->floatOrNull(data_get($attributes, 'price_change_percentage.h24')),
            liquidityUsd: $this->floatOrNull($attributes['reserve_in_usd'] ?? null),
            volume24h: $this->floatOrNull(data_get($attributes, 'volume_usd.h24')),
            volume1h: $this->floatOrNull(data_get($attributes, 'volume_usd.h1')),
            volume6h: $this->floatOrNull(data_get($attributes, 'volume_usd.h6')),
            fdvUsd: $this->floatOrNull($attributes['fdv_usd'] ?? null),
            marketCapUsd: $this->floatOrNull($attributes['market_cap_usd'] ?? null),
            txns24h: $txns > 0 ? $txns : null,
            buys24h: $buysInt,
            sells24h: $sellsInt,
            pairedAt: $pairedAt,
            isTrending: $trending,
            rank: $trending ? $rank : null,
        );
    }

    /**
     * @param  list<DexPairData>  $pools
     */
    private function sumPoolLiquidity(array $pools): ?float
    {
        $sum = 0.0;
        $any = false;
        foreach ($pools as $pool) {
            if ($pool->liquidityUsd !== null) {
                $sum += $pool->liquidityUsd;
                $any = true;
            }
        }

        return $any ? $sum : null;
    }

    /**
     * @param  list<DexPairData>  $pools
     */
    private function sumPoolVolume(array $pools): ?float
    {
        $sum = 0.0;
        $any = false;
        foreach ($pools as $pool) {
            if ($pool->volume24h !== null) {
                $sum += $pool->volume24h;
                $any = true;
            }
        }

        return $any ? $sum : null;
    }

    private function networkIdFromExternalId(string $externalId): ?string
    {
        $pos = strpos($externalId, '_');
        if ($pos === false || $pos === 0) {
            return null;
        }

        return substr($externalId, 0, $pos);
    }

    /**
     * @param  list<mixed>  $included
     * @return array<string, array<string, mixed>>
     */
    private function indexIncluded(array $included): array
    {
        $index = [];

        foreach ($included as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;
            $id = $item['id'] ?? null;
            if (! is_string($type) || ! is_string($id) || $type === '' || $id === '') {
                continue;
            }

            $index[$type . ':' . $id] = $item;
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $relationships
     * @param  array<string, array<string, mixed>>  $included
     * @return array<string, mixed>|null
     */
    private function related(array $relationships, array $included, string $key): ?array
    {
        $type = data_get($relationships, $key . '.data.type');
        $id = data_get($relationships, $key . '.data.id');

        if (! is_string($type) || ! is_string($id)) {
            return null;
        }

        return $included[$type . ':' . $id] ?? null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $baseToken
     * @param  array<string, mixed>|null  $quoteToken
     * @return array{0: string, 1: string}
     */
    private function symbols(array $attributes, ?array $baseToken, ?array $quoteToken): array
    {
        $base = data_get($baseToken, 'attributes.symbol');
        $quote = data_get($quoteToken, 'attributes.symbol');

        if (is_string($base) && is_string($quote) && $base !== '' && $quote !== '') {
            return [Str::upper($base), Str::upper($quote)];
        }

        $name = $attributes['name'] ?? null;
        if (! is_string($name) || $name === '') {
            return ['', ''];
        }

        $withoutFee = preg_replace('/\s+\d+(\.\d+)?%\s*$/', '', $name) ?? $name;
        $parts = array_map('trim', explode('/', $withoutFee, 2));

        return [
            isset($parts[0]) && is_string($parts[0]) ? Str::upper($parts[0]) : '',
            isset($parts[1]) && is_string($parts[1]) ? Str::upper($parts[1]) : '',
        ];
    }

    private function dexName(?array $dex, mixed $dexId): string
    {
        $name = data_get($dex, 'attributes.name');
        if (is_string($name) && $name !== '') {
            return $name;
        }

        if (is_string($dexId) && $dexId !== '') {
            return Str::title(str_replace(['-', '_'], ' ', $dexId));
        }

        return 'Unknown DEX';
    }

    private function chainLabel(string $networkId): string
    {
        if ($networkId === '') {
            return 'Unknown';
        }

        return DexNetwork::label($networkId);
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * A Pro key must hit CoinGecko's onchain host. The public GeckoTerminal
     * host ignores the key and keeps the free-tier ceiling, which is why a
     * 500/min plan still saw 429s on token detail.
     */
    private function baseUrl(): string
    {
        if ($this->hasApiKey()) {
            return rtrim((string) config(
                'marketdata.geckoterminal.onchain_base_url',
                'https://pro-api.coingecko.com/api/v3/onchain',
            ), '/');
        }

        return rtrim((string) config('marketdata.geckoterminal.base_url'), '/');
    }

    private function hasApiKey(): bool
    {
        $apiKey = config('marketdata.geckoterminal.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    private function client(): PendingRequest
    {
        $retries = max(1, (int) config('marketdata.geckoterminal.retry_times', 4));
        $baseSleep = max(0, (int) config('marketdata.geckoterminal.retry_sleep_ms', 250));

        $request = app(ProviderCallCounter::class)->count(
            Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->timeout(30)
                ->retry(
                    $retries,
                    fn (int $attempt): int => $baseSleep * $attempt,
                    fn (Throwable $exception): bool => $exception instanceof RequestException
                        && $exception->response?->status() === 429,
                    throw: false,
                ),
            ProviderCallCounter::GECKOTERMINAL,
        );

        $apiKey = config('marketdata.geckoterminal.api_key');
        $header = config('marketdata.geckoterminal.api_key_header', 'x-cg-pro-api-key');

        if ($this->hasApiKey() && is_string($header) && $header !== '') {
            $request = $request->withHeaders([$header => (string) $apiKey]);
        }

        return $request;
    }
}
