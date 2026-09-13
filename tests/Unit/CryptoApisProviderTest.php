<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\CryptoApisProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CryptoApisProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketdata.cryptoapis.base_url' => 'https://rest.cryptoapis.io',
            'marketdata.cryptoapis.api_key' => 'testing-crypto-apis-key',
            'marketdata.cryptoapis.per_page' => 50,
            'marketdata.cryptoapis.max_pages' => 5,
        ]);
    }

    public function test_it_reads_a_page_past_the_ranking_because_the_two_orders_drift(): void
    {
        Http::fake([
            'rest.cryptoapis.io/market-data/metadata/assets*' => Http::sequence()
                ->push($this->assets(['BTC' => '0.07964959']))
                ->push($this->assets(['DOGE' => '-1.25']))
                ->push($this->assets(['JASMY' => '-0.13973782'])),
        ]);

        // 60 coins need two pages of 50, and the third is the headroom page.
        $changes = app(CryptoApisProvider::class)->fetchPercentChangesBySymbol(60);

        $this->assertSame(['BTC', 'DOGE', 'JASMY'], $changes->keys()->all());
        $this->assertSame(0.07964959, $changes->get('BTC')->percentChange1h);
        $this->assertSame(-0.13973782, $changes->get('JASMY')->percentChange1h);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-API-Key', 'testing-crypto-apis-key')
            && str_contains($request->url(), 'limit=50')
            && str_contains($request->url(), 'type=crypto'));
    }

    public function test_it_stops_at_the_configured_page_ceiling(): void
    {
        config(['marketdata.cryptoapis.max_pages' => 1]);

        Http::fake([
            'rest.cryptoapis.io/*' => Http::response($this->assets(['BTC' => '0.5'])),
        ]);

        app(CryptoApisProvider::class)->fetchPercentChangesBySymbol(500);

        Http::assertSentCount(1);
    }

    public function test_it_skips_assets_without_usable_numbers(): void
    {
        Http::fake([
            'rest.cryptoapis.io/*' => Http::response([
                'data' => [
                    'items' => [
                        ['symbol' => 'BTC', 'referenceId' => 'reference-btc', 'specificData' => ['1HourPriceChangeInPercentage' => '0.25']],
                        ['symbol' => '', 'specificData' => ['1HourPriceChangeInPercentage' => '9.9']],
                        ['symbol' => 'NIL', 'specificData' => ['1HourPriceChangeInPercentage' => null]],
                        ['symbol' => 'JUNK', 'specificData' => ['1HourPriceChangeInPercentage' => false]],
                        'not-an-asset',
                    ],
                ],
            ]),
        ]);

        $changes = app(CryptoApisProvider::class)->fetchPercentChangesBySymbol(10);

        $this->assertSame(['BTC'], $changes->keys()->all());
        $this->assertSame(0.25, $changes->get('BTC')->percentChange1h);
        $this->assertNull($changes->get('BTC')->percentChange7d);
    }

    public function test_it_treats_one_asset_returned_by_two_pages_as_one_asset(): void
    {
        Http::fake([
            'rest.cryptoapis.io/*' => Http::response($this->assets(['BTC' => '0.25'])),
        ]);

        $changes = app(CryptoApisProvider::class)->fetchPercentChangesBySymbol(10);

        Http::assertSentCount(2);
        $this->assertSame(0.25, $changes->get('BTC')->percentChange1h);
    }

    public function test_it_drops_a_ticker_that_two_assets_share(): void
    {
        Http::fake([
            'rest.cryptoapis.io/*' => Http::response($this->assets([
                'BTC' => '0.25',
                'eth' => '1.5',
                'ETH' => '9.5',
            ])),
        ]);

        $changes = app(CryptoApisProvider::class)->fetchPercentChangesBySymbol(10);

        $this->assertSame(['BTC'], $changes->keys()->all());
    }

    public function test_it_reports_a_failed_response(): void
    {
        Http::fake([
            'rest.cryptoapis.io/*' => Http::response(['error' => 'nope'], 401),
        ]);

        $this->expectExceptionMessage('Crypto APIs assets failed: 401');

        app(CryptoApisProvider::class)->fetchPercentChangesBySymbol(10);
    }

    public function test_it_is_unconfigured_without_a_key(): void
    {
        config(['marketdata.cryptoapis.api_key' => '']);

        $this->assertFalse(app(CryptoApisProvider::class)->isConfigured());
    }

    /**
     * @param  array<string, string>  $percentages
     * @return array<string, mixed>
     */
    private function assets(array $percentages): array
    {
        $items = [];

        foreach ($percentages as $symbol => $percent) {
            $items[] = [
                'symbol' => $symbol,
                'name' => $symbol,
                'referenceId' => 'reference-' . Str::lower($symbol) . '-' . $percent,
                'slug' => Str::lower($symbol),
                'specificData' => [
                    '1HourPriceChangeInPercentage' => $percent,
                    '1WeekPriceChangeInPercentage' => '2.5',
                ],
            ];
        }

        return ['data' => ['limit' => 50, 'offset' => 0, 'total' => count($items), 'items' => $items]];
    }
}
