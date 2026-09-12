<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CoinShow;
use App\Livewire\Home;
use App\Models\Coin;
use App\Models\MarketGlobal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_coins_from_database(): void
    {
        Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
            'percent_change_24h' => 1.5,
            'market_cap' => 1_000_000_000,
            'volume_24h' => 20_000_000,
        ]);

        MarketGlobal::query()->create([
            'total_market_cap' => 2_000_000_000_000,
            'total_volume_24h' => 90_000_000_000,
            'btc_dominance' => 50,
            'provider' => 'coingecko',
            'synced_at' => now(),
        ]);

        Livewire::test(Home::class)
            ->assertSee('Bitcoin')
            ->assertSee('BTC')
            ->assertOk();
    }

    public function test_coin_detail_page_renders(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3000,
            'description' => 'Smart contract platform',
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        Livewire::test(CoinShow::class, ['coin' => $coin])
            ->assertSee('Ethereum')
            ->assertSee('Smart contract platform')
            ->assertOk();
    }
}
