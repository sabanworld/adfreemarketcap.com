<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\CoinGeckoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoinGeckoProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'marketdata.coingecko.base_url' => 'https://pro-api.coingecko.com/api/v3',
            'marketdata.coingecko.api_key' => 'test-pro-key',
            'marketdata.coingecko.api_key_header' => 'x-cg-pro-api-key',
            'marketdata.coingecko.retry_times' => 4,
            'marketdata.coingecko.retry_sleep_ms' => 0,
        ]);
    }

    public function test_it_retries_a_429_on_market_chart_then_succeeds(): void
    {
        Http::fake([
            'pro-api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::sequence()
                ->push([
                    'status' => [
                        'error_code' => 429,
                        'error_message' => 'You\'ve exceeded the Rate Limit.',
                    ],
                ], 429)
                ->push([
                    'prices' => [
                        [1_700_000_000_000, 42000.5],
                        [1_700_086_400_000, 43000.25],
                    ],
                ]),
        ]);

        $points = app(CoinGeckoProvider::class)->fetchMarketChart('bitcoin', '1');

        $this->assertSame([
            [1_700_000_000_000, 42000.5],
            [1_700_086_400_000, 43000.25],
        ], $points);
        Http::assertSentCount(2);
    }

    public function test_it_retries_a_429_on_coin_detail_then_succeeds(): void
    {
        Http::fake([
            'pro-api.coingecko.com/api/v3/coins/bitcoin*' => Http::sequence()
                ->push('Throttled', 429)
                ->push([
                    'id' => 'bitcoin',
                    'description' => ['en' => '<p>Peer-to-peer cash.</p>'],
                ]),
        ]);

        $detail = app(CoinGeckoProvider::class)->fetchCoinDetail('bitcoin');

        $this->assertSame('bitcoin', $detail->externalId);
        $this->assertSame('Peer-to-peer cash.', $detail->description);
        Http::assertSentCount(2);
    }

    public function test_it_gives_up_after_exhausted_429_retries(): void
    {
        config(['marketdata.coingecko.retry_times' => 2]);

        Http::fake([
            'pro-api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
                'status' => [
                    'error_code' => 429,
                    'error_message' => 'You\'ve exceeded the Rate Limit.',
                ],
            ], 429),
        ]);

        $this->expectExceptionMessage('CoinGecko market chart failed: 429');

        app(CoinGeckoProvider::class)->fetchMarketChart('bitcoin', '1');
    }
}
