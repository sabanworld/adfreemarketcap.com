<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\GeckoTerminalProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeckoTerminalProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'marketdata.geckoterminal.base_url' => 'https://api.geckoterminal.com/api/v2',
            'marketdata.geckoterminal.onchain_base_url' => 'https://pro-api.coingecko.com/api/v3/onchain',
            'marketdata.geckoterminal.api_key_header' => 'x-cg-pro-api-key',
            'marketdata.geckoterminal.retry_times' => 4,
            'marketdata.geckoterminal.retry_sleep_ms' => 0,
        ]);
    }

    public function test_without_an_api_key_it_uses_the_public_geckoterminal_host(): void
    {
        config(['marketdata.geckoterminal.api_key' => '']);

        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response([
                'data' => [],
                'included' => [],
            ]),
        ]);

        app(GeckoTerminalProvider::class)->fetchTrendingPools();

        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://api.geckoterminal.com/api/v2/networks/trending_pools',
        ));
    }

    public function test_with_an_api_key_it_routes_dex_calls_through_the_pro_onchain_host(): void
    {
        config(['marketdata.geckoterminal.api_key' => 'test-pro-key']);

        Http::fake(function (Request $request) {
            $url = $request->url();

            $this->assertStringStartsWith('https://pro-api.coingecko.com/api/v3/onchain/', $url);
            $this->assertTrue($request->hasHeader('x-cg-pro-api-key', 'test-pro-key'));

            if (str_contains($url, '/top_holders')) {
                return Http::response(['data' => ['attributes' => ['holders' => []]]]);
            }

            if (str_contains($url, '/trades')) {
                return Http::response(['data' => []]);
            }

            if (str_contains($url, '/ohlcv/')) {
                return Http::response(['data' => ['attributes' => ['ohlcv_list' => []]]]);
            }

            if (str_contains($url, '/pools')) {
                return Http::response(['data' => [], 'included' => []]);
            }

            if (str_contains($url, '/tokens/')) {
                return Http::response([
                    'data' => [
                        'id' => 'robinhood_0x5fc5360d0400a0fd4f2af552add042d716f1d168',
                        'type' => 'token',
                        'attributes' => [
                            'address' => '0x5fc5360d0400a0fd4f2af552add042d716f1d168',
                            'name' => 'Fixture',
                            'symbol' => 'FIX',
                            'price_usd' => '1.0',
                        ],
                    ],
                    'included' => [],
                ]);
            }

            return Http::response(['error' => 'unexpected ' . $url], 500);
        });

        app(GeckoTerminalProvider::class)->fetchTokenDetail(
            'robinhood',
            '0x5fc5360d0400a0fd4f2af552add042d716f1d168',
        );

        Http::assertSentCount(7);
        Http::assertNotSent(fn (Request $request): bool => str_contains(
            $request->url(),
            'api.geckoterminal.com',
        ));
    }

    public function test_it_retries_a_429_then_succeeds(): void
    {
        config(['marketdata.geckoterminal.api_key' => 'test-pro-key']);

        Http::fake([
            'pro-api.coingecko.com/api/v3/onchain/networks/trending_pools*' => Http::sequence()
                ->push([
                    'status' => [
                        'error_code' => 429,
                        'error_message' => "You've exceeded the Rate Limit.",
                    ],
                ], 429)
                ->push(['data' => [], 'included' => []]),
        ]);

        $pools = app(GeckoTerminalProvider::class)->fetchTrendingPools();

        $this->assertTrue($pools->isEmpty());
        Http::assertSentCount(2);
    }

    public function test_it_gives_up_after_exhausted_429_retries(): void
    {
        config([
            'marketdata.geckoterminal.api_key' => 'test-pro-key',
            'marketdata.geckoterminal.retry_times' => 2,
        ]);

        Http::fake([
            'pro-api.coingecko.com/api/v3/onchain/networks/trending_pools*' => Http::response([
                'status' => [
                    'error_code' => 429,
                    'error_message' => "You've exceeded the Rate Limit.",
                ],
            ], 429),
        ]);

        $this->expectExceptionMessage('GeckoTerminal /networks/trending_pools failed: 429');

        app(GeckoTerminalProvider::class)->fetchTrendingPools();
    }
}
