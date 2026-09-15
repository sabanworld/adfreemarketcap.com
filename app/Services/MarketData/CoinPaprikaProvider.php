<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\CoinDetailData;
use App\Services\MarketData\DTOs\GlobalMarketData;
use App\Services\MarketData\DTOs\MarketCoinData;
use App\Support\PlainText;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CoinPaprikaProvider implements MarketDataProvider
{
    public function name(): string
    {
        return 'coinpaprika';
    }

    public function fetchMarkets(int $page, int $perPage): Collection
    {
        $response = $this->client()->get('/tickers');

        throw_unless($response->successful(), new RuntimeException(
            'CoinPaprika tickers failed: ' . $response->status() . ' ' . $response->body()
        ));

        $offset = max(0, ($page - 1) * $perPage);

        return collect($response->json() ?? [])
            ->slice($offset, $perPage)
            ->values()
            ->map(function (array $row): MarketCoinData {
                $quote = data_get($row, 'quotes.USD', []);

                return new MarketCoinData(
                    externalId: (string) ($row['id'] ?? ''),
                    symbol: Str::upper((string) ($row['symbol'] ?? '')),
                    name: (string) ($row['name'] ?? ''),
                    imageUrl: null,
                    rank: isset($row['rank']) ? (int) $row['rank'] : null,
                    price: isset($quote['price']) ? (float) $quote['price'] : null,
                    percentChange1h: isset($quote['percent_change_1h']) ? (float) $quote['percent_change_1h'] : null,
                    percentChange24h: isset($quote['percent_change_24h']) ? (float) $quote['percent_change_24h'] : null,
                    percentChange7d: isset($quote['percent_change_7d']) ? (float) $quote['percent_change_7d'] : null,
                    marketCap: isset($quote['market_cap']) ? (float) $quote['market_cap'] : null,
                    volume24h: isset($quote['volume_24h']) ? (float) $quote['volume_24h'] : null,
                    circulatingSupply: isset($row['circulating_supply']) ? (float) $row['circulating_supply'] : null,
                    sparkline7d: null,
                );
            })
            ->filter(fn (MarketCoinData $coin): bool => filled($coin->externalId));
    }

    public function fetchGlobal(): GlobalMarketData
    {
        $response = $this->client()->get('/global');

        throw_unless($response->successful(), new RuntimeException(
            'CoinPaprika global failed: ' . $response->status() . ' ' . $response->body()
        ));

        $data = $response->json() ?? [];

        return new GlobalMarketData(
            totalMarketCap: isset($data['market_cap_usd']) ? (float) $data['market_cap_usd'] : null,
            totalVolume24h: isset($data['volume_24h_usd']) ? (float) $data['volume_24h_usd'] : null,
            btcDominance: isset($data['bitcoin_dominance_percentage']) ? (float) $data['bitcoin_dominance_percentage'] : null,
            activeCryptocurrencies: isset($data['cryptocurrencies_number']) ? (int) $data['cryptocurrencies_number'] : null,
            marketCapChangePercentage24h: isset($data['market_cap_change_24h'])
                ? (float) $data['market_cap_change_24h']
                : null,
        );
    }

    public function fetchCoinDetail(string $externalId): CoinDetailData
    {
        $detail = $this->client()->get('/coins/' . $externalId);

        throw_unless($detail->successful(), new RuntimeException(
            'CoinPaprika coin detail failed: ' . $detail->status() . ' ' . $detail->body()
        ));

        $description = data_get($detail->json(), 'description');

        return new CoinDetailData(
            externalId: $externalId,
            description: is_string($description) ? PlainText::fromHtml($description) : null,
            chart7d: null,
        );
    }

    private function client(): PendingRequest
    {
        return app(ProviderCallCounter::class)->count(
            Http::baseUrl((string) config('marketdata.coinpaprika.base_url'))
                ->acceptJson()
                ->timeout(30),
            ProviderCallCounter::COINPAPRIKA,
        );
    }
}
