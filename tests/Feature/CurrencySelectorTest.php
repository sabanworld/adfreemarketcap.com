<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CurrencySelector;
use App\Models\Coin;
use App\Models\CurrencyRate;
use App\Models\SyncRun;
use App\Services\Currency\CurrencyRateSyncService;
use App\Services\Currency\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CurrencySelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_rate_sync_stores_rates_relative_to_usd(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/exchange_rates' => Http::response(
                $this->fixture('coingecko_exchange_rates.json'),
            ),
        ]);

        $run = app(CurrencyRateSyncService::class)->syncRates();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame('currency_rates', $run->type);

        // CoinGecko quotes everything against BTC: 66000 EUR/BTC ÷ 77000 USD/BTC.
        $this->assertEqualsWithDelta(66000 / 77000, $this->rate('eur'), 0.000001);
        $this->assertEqualsWithDelta(1 / 77000, $this->rate('btc'), 0.000000001);
        $this->assertEqualsWithDelta(100_000_000 / 77000, $this->rate('sats'), 0.000001);
        $this->assertSame(1.0, $this->rate('usd'));

        // Unpriced or unconfigured units never reach the table.
        $this->assertDatabaseMissing('currency_rates', ['code' => 'xau']);
        $this->assertDatabaseMissing('currency_rates', ['code' => 'xag']);
    }

    public function test_rate_sync_fails_when_the_payload_has_no_usd_rate(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/exchange_rates' => Http::response([
                'rates' => ['btc' => ['value' => 1.0]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(CurrencyRateSyncService::class)->syncRates();
        } finally {
            $this->assertDatabaseHas('sync_runs', [
                'type' => 'currency_rates',
                'status' => SyncRun::STATUS_FAILED,
            ]);
        }
    }

    public function test_selector_persists_the_chosen_unit_for_the_session(): void
    {
        $this->seedRates();

        Livewire::test(CurrencySelector::class)
            ->assertSet('currency', 'usd')
            ->set('currency', 'eur')
            ->assertSet('currency', 'eur')
            ->assertOk();

        $this->assertSame('eur', session('display_currency'));
        $this->assertSame('eur', app(CurrencyService::class)->activeCode());
    }

    public function test_selector_rejects_units_without_a_synced_rate(): void
    {
        $this->seedRates();

        // gbp is configured but has no rate row, so it must not become active.
        Livewire::test(CurrencySelector::class)
            ->set('currency', 'gbp')
            ->assertSet('currency', 'usd');

        Livewire::test(CurrencySelector::class)
            ->set('currency', 'not-a-currency')
            ->assertSet('currency', 'usd');

        $this->assertSame('usd', app(CurrencyService::class)->activeCode());
    }

    public function test_market_figures_render_in_the_selected_unit(): void
    {
        $this->seedRates();
        $this->seedCoin();

        $this->withSession(['display_currency' => 'eur'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('€42,857.14')     // 50,000 USD at 0.857142…
            ->assertDontSee('$50,000.00');

        $this->withSession(['display_currency' => 'sats'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('64.94M sats');   // 50,000 USD ÷ 77,000 USD/BTC, in satoshis
    }

    public function test_coin_meta_description_stays_in_usd(): void
    {
        // Ethereum has a Nostr feed, and the test queue runs inline, so the render-time sync
        // would reach for a relay gateway.
        Queue::fake();

        $this->seedRates();
        // Not Bitcoin: visiting that page can trigger the treasury insight refresh.
        $coin = $this->seedCoin(slug: 'ethereum', symbol: 'ETH', name: 'Ethereum');

        $response = $this->withSession(['display_currency' => 'eur'])
            ->get(route('coins.show', $coin))
            ->assertOk();

        // Body figures follow the selector, crawlable meta copy does not.
        $response->assertSee('€42,857.14');
        $this->assertStringContainsString('$50,000.00', $this->metaDescription($response->getContent()));
    }

    public function test_visitors_without_a_choice_see_usd(): void
    {
        $this->seedRates();
        $this->seedCoin();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('$50,000.00');
    }

    private function seedRates(): void
    {
        foreach (['usd' => 1.0, 'eur' => 66000 / 77000, 'btc' => 1 / 77000, 'sats' => 100_000_000 / 77000] as $code => $rate) {
            CurrencyRate::query()->create([
                'code' => $code,
                'provider' => 'coingecko',
                'rate_per_usd' => $rate,
                'synced_at' => now(),
            ]);
        }

        app(CurrencyService::class)->flushCache();
    }

    private function seedCoin(string $slug = 'bitcoin', string $symbol = 'BTC', string $name = 'Bitcoin'): Coin
    {
        return Coin::query()->create([
            'slug' => $slug,
            'symbol' => $symbol,
            'name' => $name,
            'rank' => 1,
            'price' => 50000,
            'percent_change_24h' => 1.5,
            'market_cap' => 1_000_000_000_000,
            'volume_24h' => 20_000_000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);
    }

    private function rate(string $code): float
    {
        return (float) CurrencyRate::query()->where('code', $code)->value('rate_per_usd');
    }

    private function metaDescription(string $html): string
    {
        preg_match('/<meta name="description" content="([^"]*)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        $decoded = json_decode((string) file_get_contents(base_path('tests/Fixtures/marketdata/' . $name)), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
