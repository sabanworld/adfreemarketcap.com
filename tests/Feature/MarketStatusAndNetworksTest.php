<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CoinShow;
use App\Livewire\Home;
use App\Models\Coin;
use App\Models\CoinPlatform;
use App\Models\CoinProviderId;
use App\Models\MarketGlobal;
use App\Models\MarketStatusSnapshot;
use App\Services\MarketData\CoinPlatformSyncService;
use App\Services\MarketData\MarketStatusSyncService;
use App\Services\MarketData\MarketSyncService;
use App\Services\Nostr\NostrFeedSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketStatusAndNetworksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_market_status_sync_persists_fear_greed_afmc10_and_season(): void
    {
        Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
            'percent_change_24h' => 1.0,
            'percent_change_90d' => 10.0,
            'market_cap' => 1_000_000_000,
        ]);
        Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3000,
            'percent_change_24h' => -1.0,
            'percent_change_90d' => 20.0,
            'market_cap' => 500_000_000,
        ]);

        config([
            'marketdata.afmc10' => ['bitcoin', 'ethereum'],
            'marketdata.altcoin_season.top_n' => 50,
            'marketdata.altcoin_season.exclude_symbols' => ['USDT'],
            'marketdata.alternative_me.base_url' => 'https://api.alternative.me',
        ]);

        Http::fake([
            'api.alternative.me/fng/*' => Http::response([
                'name' => 'Fear and Greed Index',
                'data' => [[
                    'value' => '68',
                    'value_classification' => 'Greed',
                    'timestamp' => '1710000000',
                ]],
            ]),
        ]);

        $run = app(MarketStatusSyncService::class)->sync();

        $this->assertSame('succeeded', $run->status);
        $snapshot = MarketStatusSnapshot::latestSnapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(68, $snapshot->fear_greed_value);
        $this->assertSame('Greed', $snapshot->fear_greed_classification);
        $this->assertNotNull($snapshot->afmc10_value);
        $this->assertNotNull($snapshot->altcoin_season_index);
    }

    public function test_home_shows_market_status_and_filters_by_network(): void
    {
        $ethToken = Coin::query()->create([
            'slug' => 'pepe',
            'symbol' => 'PEPE',
            'name' => 'Pepe',
            'rank' => 1,
            'price' => 0.01,
            'market_cap' => 1_000_000,
        ]);
        $solToken = Coin::query()->create([
            'slug' => 'bonk',
            'symbol' => 'BONK',
            'name' => 'Bonk',
            'rank' => 2,
            'price' => 0.02,
            'market_cap' => 900_000,
        ]);

        CoinPlatform::query()->create([
            'coin_id' => $ethToken->id,
            'platform_id' => 'ethereum',
            'contract_address' => '0xpepe',
        ]);
        CoinPlatform::query()->create([
            'coin_id' => $solToken->id,
            'platform_id' => 'solana',
            'contract_address' => 'bonk111',
        ]);

        MarketGlobal::query()->create([
            'total_market_cap' => 2_000_000_000_000,
            'market_cap_change_percentage_24h' => -0.07,
            'provider' => 'coingecko',
            'synced_at' => now(),
        ]);
        MarketStatusSnapshot::query()->create([
            'fear_greed_value' => 68,
            'fear_greed_classification' => 'Greed',
            'afmc10_value' => 101.5,
            'afmc10_change_24h' => 0.4,
            'afmc10_change_7d' => 1.2,
            'afmc10_change_30d' => -3.5,
            'afmc10_change_200d' => 12.0,
            'afmc10_change_1y' => 45.0,
            'altcoin_season_index' => 41,
            'provider' => 'alternative.me+local',
            'synced_at' => now(),
        ]);

        Livewire::test(Home::class)
            ->assertSee('Market status')
            ->assertSee('Greed')
            ->assertSee('AFMC10')
            ->assertSee('24h')
            ->assertSee('7d')
            ->assertSee('1m')
            ->assertSee('6m')
            ->assertSee('1y')
            ->assertSee('Index by Alternative.me')
            ->assertSee('Pepe')
            ->assertSee('Bonk')
            ->call('setNetwork', 'ethereum')
            ->assertSet('network', 'ethereum')
            ->assertViewHas('coins', function ($coins) use ($ethToken): bool {
                return $coins->total() === 1 && (int) $coins->first()->id === $ethToken->id;
            });
    }

    public function test_platform_sync_maps_coingecko_list_to_known_coins(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'pepe',
            'symbol' => 'PEPE',
            'name' => 'Pepe',
            'rank' => 10,
            'price' => 0.01,
        ]);
        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coingecko',
            'external_id' => 'pepe',
        ]);

        config(['marketdata.coingecko.base_url' => 'https://api.coingecko.com/api/v3']);

        Http::fake([
            'api.coingecko.com/api/v3/coins/list*' => Http::response([
                [
                    'id' => 'pepe',
                    'symbol' => 'pepe',
                    'name' => 'Pepe',
                    'platforms' => [
                        'ethereum' => '0xpepe',
                        'solana' => '',
                    ],
                ],
                [
                    'id' => 'unknown-coin',
                    'symbol' => 'unk',
                    'name' => 'Unknown',
                    'platforms' => ['ethereum' => '0xunk'],
                ],
            ]),
        ]);

        $run = app(CoinPlatformSyncService::class)->sync();

        $this->assertSame('succeeded', $run->status);
        $this->assertDatabaseHas('coin_platforms', [
            'coin_id' => $coin->id,
            'platform_id' => 'ethereum',
            'contract_address' => '0xpepe',
        ]);
        $this->assertDatabaseMissing('coin_platforms', [
            'platform_id' => 'solana',
        ]);
    }

    public function test_nostr_sync_and_coin_show_community_card(): void
    {
        Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
            'market_cap' => 1_000_000_000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        config([
            'nostr.backends' => [
                [
                    'driver' => 'divine',
                    'base_url' => 'https://gateway.divine.video',
                ],
            ],
            'nostr.feeds' => [
                'bitcoin' => [
                    'authors' => [
                        [
                            'npub' => 'npub1t6el40knsq8hmrpr0m6tt3t0tr4pdeyhlt2qelwhgtwawddqx0xsv03scu',
                            'label' => 'Rigly',
                        ],
                    ],
                    'hashtags' => ['bitcoin'],
                ],
            ],
        ]);

        $pubkey = '5eb3fabed3800f7d8c237ef4b5c56f58ea16e497fad40cfdd742ddd735a033cd';

        Http::fake([
            'gateway.divine.video/query*' => Http::response([
                'events' => [[
                    'id' => str_repeat('a', 64),
                    'pubkey' => $pubkey,
                    'created_at' => now()->timestamp,
                    'kind' => 1,
                    'content' => 'Bitcoin is looking strong #bitcoin',
                    'tags' => [],
                    'sig' => str_repeat('b', 128),
                ]],
            ]),
        ]);

        $run = app(NostrFeedSyncService::class)->sync('bitcoin');
        $this->assertSame('succeeded', $run->status);
        $this->assertDatabaseHas('nostr_notes', [
            'coin_slug' => 'bitcoin',
            'event_id' => str_repeat('a', 64),
        ]);

        Queue::fake();

        Livewire::test(CoinShow::class, ['coin' => Coin::query()->where('slug', 'bitcoin')->firstOrFail()])
            ->assertSee('Community')
            ->assertSee('Bitcoin is looking strong')
            ->assertSee('Rigly');
    }

    public function test_global_sync_stores_market_cap_change_percentage(): void
    {
        config([
            'marketdata.primary' => 'coingecko',
            'marketdata.failover' => 'coinpaprika',
            'marketdata.coingecko.base_url' => 'https://api.coingecko.com/api/v3',
        ]);

        Http::fake([
            'api.coingecko.com/api/v3/global' => Http::response([
                'data' => [
                    'total_market_cap' => ['usd' => 2_000_000_000_000],
                    'total_volume' => ['usd' => 90_000_000_000],
                    'market_cap_percentage' => ['btc' => 52.5],
                    'market_cap_change_percentage_24h_usd' => -0.07,
                    'active_cryptocurrencies' => 10000,
                ],
            ]),
        ]);

        $run = app(MarketSyncService::class)->syncGlobal();
        $this->assertSame('succeeded', $run->status);

        $global = MarketGlobal::latestSnapshot();
        $this->assertNotNull($global);
        $this->assertEqualsWithDelta(-0.07, (float) $global->market_cap_change_percentage_24h, 0.0001);
    }
}
