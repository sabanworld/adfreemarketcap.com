<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiblingPriceFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_is_absent_without_a_token(): void
    {
        config(['sibling_api.enabled' => false, 'sibling_api.token' => null]);

        $this->getJson('/api/sibling/prices')
            ->assertNotFound();
    }

    public function test_feed_rejects_a_bad_token(): void
    {
        config([
            'sibling_api.enabled' => true,
            'sibling_api.token' => 'correct-token',
            'sibling_api.allowed_symbols' => ['BTC'],
        ]);

        $this->withToken('wrong-token')
            ->getJson('/api/sibling/prices')
            ->assertUnauthorized();
    }

    public function test_feed_returns_allowed_coin_prices(): void
    {
        config([
            'sibling_api.enabled' => true,
            'sibling_api.token' => 'correct-token',
            'sibling_api.allowed_symbols' => ['BTC', 'LTC'],
        ]);

        Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 78000.5,
            'percent_change_24h' => 1.25,
            'market_synced_at' => now(),
            'last_provider' => 'coingecko',
        ]);

        Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3500,
            'percent_change_24h' => -0.5,
            'market_synced_at' => now(),
            'last_provider' => 'coingecko',
        ]);

        $this->withToken('correct-token')
            ->getJson('/api/sibling/prices?symbols=BTC,ETH')
            ->assertOk()
            ->assertJsonPath('coins.0.symbol', 'BTC')
            ->assertJsonPath('coins.0.price_usd', 78000.5)
            ->assertJsonMissing(['symbol' => 'ETH']);
    }
}
