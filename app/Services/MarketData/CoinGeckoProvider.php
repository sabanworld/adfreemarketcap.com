<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\CoinDetailData;
use App\Services\MarketData\DTOs\GlobalMarketData;
use App\Services\MarketData\DTOs\MarketCoinData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CoinGeckoProvider implements MarketDataProvider
{
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
            'CoinGecko markets failed: '.$response->status().' '.$response->body()
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
            'CoinGecko global failed: '.$response->status().' '.$response->body()
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
        $detail = $this->client()->get('/coins/'.$externalId, [
            'localization' => 'false',
            'tickers' => 'false',
            'market_data' => 'false',
            'community_data' => 'false',
            'developer_data' => 'false',
        ]);

        throw_unless($detail->successful(), new RuntimeException(
            'CoinGecko coin detail failed: '.$detail->status().' '.$detail->body()
        ));

        $chart = $this->client()->get('/coins/'.$externalId.'/market_chart', [
            'vs_currency' => 'usd',
            'days' => 7,
        ]);

        throw_unless($chart->successful(), new RuntimeException(
            'CoinGecko market chart failed: '.$chart->status().' '.$chart->body()
        ));

        $description = data_get($detail->json(), 'description.en');
        $prices = $chart->json('prices');

        return new CoinDetailData(
            externalId: $externalId,
            description: is_string($description) ? strip_tags($description) : null,
            chart7d: is_array($prices) ? $prices : null,
        );
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
