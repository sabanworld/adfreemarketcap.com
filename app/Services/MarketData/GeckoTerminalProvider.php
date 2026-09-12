<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\DexPairData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GeckoTerminalProvider implements DexDataProvider
{
    /**
     * @var array<string, string>
     */
    private const NETWORK_LABELS = [
        'eth' => 'Ethereum',
        'solana' => 'Solana',
        'bsc' => 'BNB Chain',
        'base' => 'Base',
        'arbitrum' => 'Arbitrum',
        'polygon_pos' => 'Polygon',
        'avalanche' => 'Avalanche',
        'optimism' => 'Optimism',
        'ton' => 'TON',
        'sui-network' => 'Sui',
        'robinhood' => 'Robinhood Chain',
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
        $networkId = is_string($networkId) ? $networkId : '';

        [$baseSymbol, $quoteSymbol] = $this->symbols($attributes, $baseToken, $quoteToken);
        if ($baseSymbol === '' || $quoteSymbol === '') {
            return null;
        }

        $pair = $baseSymbol . '/' . $quoteSymbol;
        $dexName = $this->dexName($dex, data_get($relationships, 'dex.data.id'));
        $chain = $this->chainLabel($networkId);
        $coingeckoId = data_get($baseToken, 'attributes.coingecko_coin_id');

        $buys = data_get($attributes, 'transactions.h24.buys');
        $sells = data_get($attributes, 'transactions.h24.sells');
        $txns = (is_numeric($buys) ? (int) $buys : 0) + (is_numeric($sells) ? (int) $sells : 0);

        $createdAt = $attributes['pool_created_at'] ?? null;
        $pairedAt = is_string($createdAt) && $createdAt !== ''
            ? Carbon::parse($createdAt)
            : null;

        $address = $attributes['address'] ?? null;

        return new DexPairData(
            externalId: $externalId,
            pair: $pair,
            baseSymbol: $baseSymbol,
            quoteSymbol: $quoteSymbol,
            dex: $dexName,
            chain: $chain,
            contractAddress: is_string($address) && $address !== '' ? $address : null,
            auditStatus: is_string($coingeckoId) && $coingeckoId !== '' ? 'partial' : 'unverified',
            price: $this->floatOrNull($attributes['base_token_price_usd'] ?? null),
            percentChange24h: $this->floatOrNull(data_get($attributes, 'price_change_percentage.h24')),
            liquidityUsd: $this->floatOrNull($attributes['reserve_in_usd'] ?? null),
            volume24h: $this->floatOrNull(data_get($attributes, 'volume_usd.h24')),
            txns24h: $txns > 0 ? $txns : null,
            pairedAt: $pairedAt,
            isTrending: $trending,
            rank: $trending ? $rank : null,
        );
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

        // "PONIE / WETH 0.25%" → base/quote
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

        return self::NETWORK_LABELS[$networkId]
            ?? Str::title(str_replace(['-', '_'], ' ', $networkId));
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

    private function client(): PendingRequest
    {
        $request = Http::baseUrl((string) config('marketdata.geckoterminal.base_url'))
            ->acceptJson()
            ->timeout(30);

        $apiKey = config('marketdata.geckoterminal.api_key');
        $header = config('marketdata.geckoterminal.api_key_header', 'x-cg-pro-api-key');

        if (is_string($apiKey) && $apiKey !== '' && is_string($header) && $header !== '') {
            $request = $request->withHeaders([$header => $apiKey]);
        }

        return $request;
    }
}
