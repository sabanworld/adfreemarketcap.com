<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coin;
use App\Models\CurrencyRate;
use App\Services\Currency\CurrencyService;
use App\Services\Currency\MarketDisplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * With a crypto baseline, history and percentages are expressed against that
 * asset, so an asset priced against itself has to be flat.
 */
class CurrencyBaselineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<float>
     */
    private const SPARKLINE = [60000.0, 70000.0, 50000.0];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_bitcoin_priced_in_btc_is_flat(): void
    {
        $this->seedRates();
        $this->seedCoin('bitcoin', 'BTC', 'Bitcoin');

        $display = $this->displayIn('btc');

        $this->assertTrue($display->usesBaselineHistory());
        $this->assertSame([1.0, 1.0, 1.0], array_column($display->chart($this->chart()), 1));
        $this->assertSame([1.0, 1.0, 1.0], $display->sparkline(self::SPARKLINE));
        $this->assertSame(0.0, $display->change(5.0));
        $this->assertSame(0.0, $display->change(-10.0, '7d'));
    }

    public function test_bitcoin_priced_in_sats_is_flat_at_one_hundred_million(): void
    {
        $this->seedRates();
        $this->seedCoin('bitcoin', 'BTC', 'Bitcoin');

        $display = $this->displayIn('sats');

        $this->assertSame([1e8, 1e8, 1e8], $display->sparkline(self::SPARKLINE));
        $this->assertSame(0.0, $display->change(5.0));
    }

    public function test_other_coins_gain_or_lose_against_the_baseline(): void
    {
        $this->seedRates();
        $this->seedCoin('bitcoin', 'BTC', 'Bitcoin');

        $display = $this->displayIn('btc');

        // Bitcoin is the seeded baseline at +5% over 24h.
        $this->assertEqualsWithDelta(4.7619, $display->change(10.0), 0.0001);
        $this->assertEqualsWithDelta(-4.7619, $display->change(0.0), 0.0001);

        // Half the dollar price at the same timestamp is half a bitcoin.
        $halved = array_map(static fn (array $point): array => [$point[0], $point[1] / 2], $this->chart());
        $this->assertSame([0.5, 0.5, 0.5], array_column($display->chart($halved), 1));
    }

    public function test_fiat_units_keep_usd_percentages_and_scale_by_the_current_rate(): void
    {
        $this->seedRates();
        $this->seedCoin('bitcoin', 'BTC', 'Bitcoin');

        $display = $this->displayIn('eur');

        $this->assertFalse($display->usesBaselineHistory());
        $this->assertNull($display->baselineCode());
        $this->assertSame(10.0, $display->change(10.0));
        $this->assertEqualsWithDelta(60000 * (66000 / 77000), $display->chart($this->chart())[0][1], 0.0001);
    }

    public function test_missing_baseline_data_falls_back_to_the_current_rate(): void
    {
        $this->seedRates();
        // No ethereum row, so the ETH baseline cannot be resolved.
        $display = $this->displayIn('eth');

        $this->assertFalse($display->usesBaselineHistory());
        $this->assertSame(10.0, $display->change(10.0));
        $this->assertCount(3, $display->chart($this->chart()));
    }

    public function test_coin_page_shows_a_flat_series_without_the_conversion_caveat(): void
    {
        // Ethereum has a Nostr feed, and the test queue runs inline, so the render-time sync
        // would reach for a relay gateway.
        Queue::fake();

        $this->seedRates();
        $coin = $this->seedCoin('ethereum', 'ETH', 'Ethereum');

        $response = $this->withSession(['display_currency' => 'eth'])
            ->get(route('coins.show', $coin))
            ->assertOk()
            ->assertSee('Ξ1.00')
            ->assertSee('vs ETH')
            ->assertSee('0.00%')
            ->assertDontSee('at the current');

        // Chart.js is handed the series inline, as values: JSON.parse('[…]').
        preg_match(
            '/values:\s*JSON\.parse\(\'(\[.*?\])\'\)/',
            html_entity_decode((string) $response->getContent()),
            $matches,
        );
        $values = json_decode($matches[1] ?? '[]', true);

        $this->assertSame([1, 1, 1], $values);
    }

    /**
     * Three hourly points. The coin page plots the last seven days only, so the
     * fixture moves with the clock instead of sitting on fixed timestamps.
     *
     * @return list<array{0: int, 1: float}>
     */
    private function chart(): array
    {
        $start = (int) now()->subDay()->getTimestampMs();

        return [[$start, 60000.0], [$start + 3_600_000, 70000.0], [$start + 7_200_000, 50000.0]];
    }

    private function displayIn(string $code): MarketDisplayService
    {
        app(CurrencyService::class)->remember($code);

        return app(MarketDisplayService::class);
    }

    private function seedRates(): void
    {
        $rates = [
            'usd' => 1.0,
            'eur' => 66000 / 77000,
            'btc' => 1 / 50000,
            'sats' => 100_000_000 / 50000,
            'eth' => 1 / 50000,
        ];

        foreach ($rates as $code => $rate) {
            CurrencyRate::query()->create([
                'code' => $code,
                'provider' => 'coingecko',
                'rate_per_usd' => $rate,
                'synced_at' => now(),
            ]);
        }

        app(CurrencyService::class)->flushCache();
    }

    private function seedCoin(string $slug, string $symbol, string $name): Coin
    {
        return Coin::query()->create([
            'slug' => $slug,
            'symbol' => $symbol,
            'name' => $name,
            'rank' => 1,
            'price' => 50000,
            'percent_change_1h' => 0.5,
            'percent_change_24h' => 5,
            'percent_change_7d' => -10,
            'market_cap' => 1_000_000_000_000,
            'volume_24h' => 20_000_000,
            'chart_7d' => $this->chart(),
            'sparkline_7d' => self::SPARKLINE,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);
    }
}
