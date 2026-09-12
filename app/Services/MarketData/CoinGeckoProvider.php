<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\Currency\ExchangeRateProvider;
use App\Services\MarketData\DTOs\CoinDetailData;
use App\Services\MarketData\DTOs\CoinTickerData;
use App\Services\MarketData\DTOs\GlobalMarketData;
use App\Services\MarketData\DTOs\MarketCoinData;
use App\Services\MarketData\Exceptions\ProviderCoinNotFoundException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CoinGeckoProvider implements ExchangeRateProvider, MarketDataProvider
{
    /**
     * Keeps base/target symbols inside the coin_tickers column widths.
     */
    private const MAX_SYMBOL_LENGTH = 32;

    public function name(): string
    {
        return 'coingecko';
    }

    public function fetchMarkets(int $page, int $perPage): Collection
    {
        $response = $this->client()->get('/coins/markets', [
            'vs_currency' => 'usd',
            'order' => 'market_cap_desc',
            'per_page' => $perPage,
            'page' => $page,
            'sparkline' => 'true',
            'price_change_percentage' => '1h,24h,7d',
        ]);

        throw_unless($response->successful(), new RuntimeException(
            'CoinGecko markets failed: ' . $response->status() . ' ' . $response->body()
        ));

        return collect($response->json() ?? [])->map(function (array $row): MarketCoinData {
            $sparkline = data_get($row, 'sparkline_in_7d.price');

            return new MarketCoinData(
                externalId: (string) ($row['id'] ?? ''),
                symbol: Str::upper((string) ($row['symbol'] ?? '')),
                name: (string) ($row['name'] ?? ''),
                imageUrl: isset($row['image']) && is_string($row['image']) ? $row['image'] : null,
                rank: isset($row['market_cap_rank']) ? (int) $row['market_cap_rank'] : null,
                price: isset($row['current_price']) ? (float) $row['current_price'] : null,
                percentChange1h: isset($row['price_change_percentage_1h_in_currency'])
                    ? (float) $row['price_change_percentage_1h_in_currency']
                    : null,
                percentChange24h: isset($row['price_change_percentage_24h_in_currency'])
                    ? (float) $row['price_change_percentage_24h_in_currency']
                    : (isset($row['price_change_percentage_24h']) ? (float) $row['price_change_percentage_24h'] : null),
                percentChange7d: isset($row['price_change_percentage_7d_in_currency'])
                    ? (float) $row['price_change_percentage_7d_in_currency']
                    : null,
                marketCap: isset($row['market_cap']) ? (float) $row['market_cap'] : null,
                volume24h: isset($row['total_volume']) ? (float) $row['total_volume'] : null,
                circulatingSupply: isset($row['circulating_supply']) ? (float) $row['circulating_supply'] : null,
                sparkline7d: is_array($sparkline) ? array_values(array_map('floatval', $sparkline)) : null,
            );
        })->filter(fn (MarketCoinData $coin): bool => filled($coin->externalId));
    }

    public function fetchGlobal(): GlobalMarketData
    {
        $response = $this->client()->get('/global');

        throw_unless($response->successful(), new RuntimeException(
            'CoinGecko global failed: ' . $response->status() . ' ' . $response->body()
        ));

        $data = $response->json('data') ?? [];

        return new GlobalMarketData(
            totalMarketCap: isset($data['total_market_cap']['usd']) ? (float) $data['total_market_cap']['usd'] : null,
            totalVolume24h: isset($data['total_volume']['usd']) ? (float) $data['total_volume']['usd'] : null,
            btcDominance: isset($data['market_cap_percentage']['btc']) ? (float) $data['market_cap_percentage']['btc'] : null,
            activeCryptocurrencies: isset($data['active_cryptocurrencies']) ? (int) $data['active_cryptocurrencies'] : null,
        );
    }

    public function fetchCoinDetail(string $externalId): CoinDetailData
    {
        $detail = $this->client()->get('/coins/' . $externalId, [
            'localization' => 'false',
            'tickers' => 'false',
            'market_data' => 'false',
            'community_data' => 'false',
            'developer_data' => 'false',
        ]);

        if ($detail->status() === 404) {
            throw new ProviderCoinNotFoundException($this->name(), $externalId);
        }

        throw_unless($detail->successful(), new RuntimeException(
            'CoinGecko coin detail failed: ' . $detail->status() . ' ' . $detail->body()
        ));

        $chart = $this->client()->get('/coins/' . $externalId . '/market_chart', [
            'vs_currency' => 'usd',
            'days' => 7,
        ]);

        throw_unless($chart->successful(), new RuntimeException(
            'CoinGecko market chart failed: ' . $chart->status() . ' ' . $chart->body()
        ));

        $description = data_get($detail->json(), 'description.en');
        $prices = $chart->json('prices');

        return new CoinDetailData(
            externalId: $externalId,
            description: is_string($description) ? strip_tags($description) : null,
            chart7d: is_array($prices) ? $prices : null,
        );
    }

    /**
     * @return Collection<int, CoinTickerData>
     */
    public function fetchCoinTickers(string $externalId, int $page = 1): Collection
    {
        $response = $this->client()->get('/coins/' . $externalId . '/tickers', [
            'include_exchange_logo' => 'false',
            'page' => max(1, $page),
            'order' => 'volume_desc',
        ]);

        // Delisted or remapped ids come back as 404. Callers remove the stale
        // provider mapping instead of retrying forever.
        if ($response->status() === 404) {
            throw new ProviderCoinNotFoundException($this->name(), $externalId);
        }

        throw_unless($response->successful(), new RuntimeException(
            'CoinGecko tickers failed: ' . $response->status() . ' ' . $response->body()
        ));

        $tickers = $response->json('tickers');
        if (! is_array($tickers)) {
            return collect();
        }

        return collect($tickers)
            ->map(function (mixed $row): ?CoinTickerData {
                if (! is_array($row)) {
                    return null;
                }

                $base = $this->tickerSymbol($row['base'] ?? null, $row['coin_id'] ?? null);
                $target = $this->tickerSymbol($row['target'] ?? null, $row['target_coin_id'] ?? null);
                $market = is_array($row['market'] ?? null) ? $row['market'] : [];
                $exchangeId = $market['identifier'] ?? null;
                $exchangeName = $market['name'] ?? null;

                if (! filled($base) || ! filled($target)) {
                    return null;
                }

                if (! is_string($exchangeId) || $exchangeId === '' || ! is_string($exchangeName) || $exchangeName === '') {
                    return null;
                }

                $lastTraded = $row['last_traded_at'] ?? null;
                $tradeUrl = $row['trade_url'] ?? null;
                $trust = $row['trust_score'] ?? null;

                return new CoinTickerData(
                    exchangeId: $exchangeId,
                    exchangeName: $exchangeName,
                    baseSymbol: $base,
                    targetSymbol: $target,
                    pair: $base . '/' . $target,
                    priceUsd: is_numeric(data_get($row, 'converted_last.usd'))
                        ? (float) data_get($row, 'converted_last.usd')
                        : null,
                    lastPrice: is_numeric($row['last'] ?? null) ? (float) $row['last'] : null,
                    volume24hUsd: is_numeric(data_get($row, 'converted_volume.usd'))
                        ? (float) data_get($row, 'converted_volume.usd')
                        : null,
                    bidAskSpreadPercent: is_numeric($row['bid_ask_spread_percentage'] ?? null)
                        ? (float) $row['bid_ask_spread_percentage']
                        : null,
                    trustScore: is_string($trust) ? $trust : null,
                    isAnomaly: (bool) ($row['is_anomaly'] ?? false),
                    isStale: (bool) ($row['is_stale'] ?? false),
                    tradeUrl: is_string($tradeUrl) && $tradeUrl !== '' ? $tradeUrl : null,
                    lastTradedAt: is_string($lastTraded) && $lastTraded !== ''
                        ? Carbon::parse($lastTraded)
                        : null,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * CoinGecko quotes every rate against BTC, so each value is divided by the
     * USD value to get the multiplier for our USD-denominated columns.
     *
     * @return Collection<string, float>
     */
    public function fetchRatesPerUsd(): Collection
    {
        $response = $this->client()->get('/exchange_rates');

        throw_unless($response->successful(), new RuntimeException(
            'CoinGecko exchange rates failed: ' . $response->status() . ' ' . $response->body()
        ));

        $rates = $response->json('rates');
        $usdPerBtc = data_get($rates, 'usd.value');

        throw_unless(is_array($rates) && is_numeric($usdPerBtc) && (float) $usdPerBtc > 0, new RuntimeException(
            'CoinGecko exchange rates did not include a usable USD rate.'
        ));

        return collect($rates)
            ->mapWithKeys(function (mixed $row, mixed $code) use ($usdPerBtc): array {
                $value = is_array($row) ? ($row['value'] ?? null) : null;

                if (! is_numeric($value) || (float) $value <= 0) {
                    return [];
                }

                return [Str::lower((string) $code) => (float) $value / (float) $usdPerBtc];
            });
    }

    /**
     * On-chain markets arrive with a contract address or XRPL currency code as
     * base/target, so fall back to the CoinGecko coin id for a displayable symbol.
     */
    private function tickerSymbol(mixed $value, mixed $coinId): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (Str::length($value) <= self::MAX_SYMBOL_LENGTH) {
            return Str::upper($value);
        }

        if (is_string($coinId) && $coinId !== '' && Str::length($coinId) <= self::MAX_SYMBOL_LENGTH) {
            return Str::upper($coinId);
        }

        return Str::upper(Str::limit($value, self::MAX_SYMBOL_LENGTH, ''));
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl((string) config('marketdata.coingecko.base_url'))
            ->acceptJson()
            ->timeout(30);

        $apiKey = config('marketdata.coingecko.api_key');

        if (filled($apiKey)) {
            $request = $request->withHeaders([
                (string) config('marketdata.coingecko.api_key_header') => (string) $apiKey,
            ]);
        }

        return $request;
    }
}
