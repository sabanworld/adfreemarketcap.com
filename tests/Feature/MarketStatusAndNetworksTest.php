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
use App\Services\MarketData\MarketOverviewService;
use App\Services\MarketData\MarketStatusSyncService;
use App\Services\MarketData\MarketSyncService;
use App\Services\MarketData\NetworkCatalogService;
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
            ->assertSee('Alternative.me')
            ->assertSee('https://alternative.me/crypto/fear-and-greed-index/', false)
            ->assertSee('Pepe')
            ->assertSee('Bonk')
            ->call('setNetwork', 'ethereum')
            ->assertSet('network', 'ethereum')
            ->assertViewHas('coins', function ($coins) use ($ethToken): bool {
                return $coins->total() === 1 && (int) $coins->first()->id === $ethToken->id;
            });
    }

    public function test_network_catalog_derives_labels_and_counts_from_ranked_coins(): void
    {
        config([
            'networks.pinned' => ['ethereum'],
            'networks.names' => ['ethereum' => 'Ethereum', 'the-open-network' => 'TON'],
            'networks.hidden' => ['boba'],
        ]);

        $first = $this->coinOnNetworks('pepe', 1, ['ethereum', 'arbitrum-nova']);
        $this->coinOnNetworks('bonk', 2, ['ethereum', 'the-open-network', 'boba']);

        // An unranked coin is not in the table, so its chains are not offered as a filter.
        $unranked = Coin::query()->create([
            'slug' => 'ghost',
            'symbol' => 'GHOST',
            'name' => 'Ghost',
            'price' => 1,
        ]);
        CoinPlatform::query()->create(['coin_id' => $unranked->id, 'platform_id' => 'sui']);

        $catalog = app(NetworkCatalogService::class);
        $networks = collect($catalog->availableNetworks());

        $this->assertSame(['ethereum', 'arbitrum-nova', 'the-open-network'], $networks->pluck('id')->all());
        $this->assertSame(2, $networks->firstWhere('id', 'ethereum')['count']);

        // Slugs are storage keys, never labels: unknown ids title-case, and the ones that do
        // not convert cleanly carry an explicit name.
        $this->assertSame('Arbitrum Nova', $networks->firstWhere('id', 'arbitrum-nova')['label']);
        $this->assertSame('TON', $networks->firstWhere('id', 'the-open-network')['label']);

        $this->assertFalse($catalog->isValidNetwork('boba'));
        $this->assertFalse($catalog->isValidNetwork('sui'));
        $this->assertTrue($catalog->isValidNetwork('all'));
        $this->assertSame('Ethereum', $catalog->networkLabel('ethereum'));
        $this->assertNull($catalog->networkLabel('sui'));
        $this->assertSame(1, $first->platforms()->where('platform_id', 'arbitrum-nova')->count());
    }

    public function test_selected_network_is_promoted_into_the_chip_row(): void
    {
        config(['networks.pinned' => ['ethereum'], 'networks.names' => []]);

        $this->coinOnNetworks('a', 1, ['ethereum']);
        $this->coinOnNetworks('b', 2, ['ethereum', 'solana']);
        $this->coinOnNetworks('c', 3, ['ethereum', 'solana', 'base']);
        $this->coinOnNetworks('d', 4, ['tron']);

        $catalog = app(NetworkCatalogService::class);

        $default = $catalog->chipRow('all', visible: 2);
        $this->assertSame(['ethereum', 'solana'], $default['shown']->pluck('id')->all());
        $this->assertSame(['base', 'tron'], $default['rest']->pluck('id')->all());

        // A chain chosen in the More menu takes the last chip, so the active filter is never
        // hidden behind a button that looks untouched.
        $promoted = $catalog->chipRow('tron', visible: 2);
        $this->assertSame(['ethereum', 'tron'], $promoted['shown']->pluck('id')->all());
        $this->assertSame(['solana', 'base'], $promoted['rest']->pluck('id')->all());
    }

    public function test_home_offers_the_filter_and_can_clear_it(): void
    {
        config(['networks.pinned' => ['ethereum'], 'networks.names' => []]);

        $this->coinOnNetworks('pepe', 1, ['ethereum']);

        Livewire::test(Home::class)
            ->assertSee('All networks')
            ->assertSeeHtml('data-afmc-chiprow')
            ->call('setNetwork', 'ethereum')
            ->set('tab', 'losers')
            // Nothing matches, so the empty state names the chain and offers the way out.
            ->assertSee('Nothing on Ethereum yet')
            ->assertSee('Clear filters')
            ->call('clearFilters')
            ->assertSet('network', 'all')
            ->assertSet('tab', 'all')
            ->assertSee('Pepe');
    }

    public function test_rows_per_page_is_a_reader_setting_within_the_offered_options(): void
    {
        for ($rank = 1; $rank <= 25; $rank++) {
            $this->coinOnNetworks('coin' . $rank, $rank, []);
        }

        Livewire::test(Home::class)
            ->assertViewHas('coins', fn ($coins): bool => $coins->perPage() === 50)
            ->set('perPage', 20)
            ->assertViewHas('coins', fn ($coins): bool => $coins->count() === 20 && $coins->total() === 25)
            // A hand-edited query string does not get to pick its own page size.
            ->set('perPage', 5000)
            ->assertSet('perPage', 50);
    }

    public function test_markets_owns_the_global_figures_the_ticker_repeats(): void
    {
        Queue::fake();

        MarketGlobal::query()->create([
            'total_market_cap' => 2_000_000_000_000,
            'total_volume_24h' => 90_000_000_000,
            'btc_dominance' => 58.5,
            'provider' => 'coingecko',
            'synced_at' => now(),
        ]);

        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
            'market_cap' => 1_000_000_000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);
        Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3000,
            'market_cap' => 400_000_000,
        ]);

        // Every number has one owner. On markets the status strip publishes market cap, 24h
        // volume and the dominance split, and the tab badge carries the tracked count, so the
        // ticker has nothing of its own left to say and is hidden rather than padded out.
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-afmc-ticker', false)
            ->assertDontSee('24h volume');

        $this->get(route('coins.show', $coin))
            ->assertOk()
            ->assertSee('data-afmc-ticker', false)
            ->assertSee('24h volume')
            ->assertSee('BTC dominance');
    }

    public function test_market_cap_panel_carries_a_line_and_a_dominance_split(): void
    {
        Queue::fake();

        // Two readings so the panel's middle track has a line to draw rather than a hole.
        MarketGlobal::query()->create([
            'total_market_cap' => 1_900_000_000_000,
            'total_volume_24h' => 88_000_000_000,
            'btc_dominance' => 58.0,
            'provider' => 'coingecko',
            'synced_at' => now()->subHours(6),
        ]);
        MarketGlobal::query()->create([
            'total_market_cap' => 2_000_000_000_000,
            'total_volume_24h' => 90_000_000_000,
            'btc_dominance' => 58.5,
            'market_cap_change_percentage_24h' => 1.4,
            'provider' => 'coingecko',
            'synced_at' => now(),
        ]);
        Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 1,
            'price' => 3000,
            'market_cap' => 200_000_000_000,
        ]);

        $overview = app(MarketOverviewService::class);

        // The percentage is measured from the endpoints of the line it sits beside, not from
        // the provider's like-for-like figure, which moved the other way here.
        $trend = $overview->marketCapTrend();
        $this->assertCount(2, $trend['series']);
        $this->assertEqualsWithDelta(5.26, $trend['change'], 0.01);

        $split = $overview->dominanceSplit();
        $this->assertSame(['BTC', 'ETH', 'Other'], array_column($split, 'label'));
        $this->assertEqualsWithDelta(58.5, $split[0]['percent'], 0.1);
        $this->assertEqualsWithDelta(10.0, $split[1]['percent'], 0.1);
        $this->assertEqualsWithDelta(31.5, $split[2]['percent'], 0.1);

        // The line takes its colour from the percentage above it, so both describe the same day.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('afmc-dominance__track', false)
            ->assertSee('afmc-status-panel__spark', false)
            ->assertSee('var(--chart-up)', false);
    }

    public function test_dominance_split_is_dropped_when_ethereum_is_unknown(): void
    {
        Queue::fake();

        MarketGlobal::query()->create([
            'total_market_cap' => 2_000_000_000_000,
            'btc_dominance' => 58.5,
            'provider' => 'coingecko',
            'synced_at' => now(),
        ]);

        $overview = app(MarketOverviewService::class);

        // A split we cannot source is not drawn, and the figure falls back to its other owner.
        $this->assertSame([], $overview->dominanceSplit());
        $this->assertContains('btc_dominance', array_column($overview->tickerItems(statusStripOnPage: true), 'key'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('BTC dominance')
            ->assertDontSee('afmc-dominance__track', false);
    }

    public function test_market_cap_line_needs_two_readings(): void
    {
        MarketGlobal::query()->create([
            'total_market_cap' => 2_000_000_000_000,
            'btc_dominance' => 58.5,
            'provider' => 'coingecko',
            'synced_at' => now(),
        ]);

        $trend = app(MarketOverviewService::class)->marketCapTrend();
        $this->assertSame([], $trend['series']);
        $this->assertNull($trend['change']);
    }

    public function test_market_cap_line_keeps_its_endpoints_when_downsampled(): void
    {
        foreach (range(0, 40) as $index) {
            MarketGlobal::query()->create([
                'total_market_cap' => 1_000_000_000_000 + $index * 1_000_000_000,
                'provider' => 'coingecko',
                'synced_at' => now()->subMinutes((40 - $index) * 10),
            ]);
        }

        $series = app(MarketOverviewService::class)->marketCapTrend(points: 24)['series'];

        // The endpoints carry the direction the percentage beside the line states.
        $this->assertCount(24, $series);
        $this->assertEqualsWithDelta(1_000_000_000_000, $series[0], 1);
        $this->assertEqualsWithDelta(1_040_000_000_000, $series[23], 1);
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
                'events' => [
                    [
                        'id' => str_repeat('a', 64),
                        'pubkey' => $pubkey,
                        'created_at' => now()->timestamp,
                        'kind' => 1,
                        'content' => 'Bitcoin is looking strong #bitcoin',
                        'tags' => [['t', 'bitcoin']],
                        'sig' => str_repeat('b', 128),
                    ],
                    [
                        'id' => str_repeat('c', 64),
                        'pubkey' => $pubkey,
                        'created_at' => now()->timestamp,
                        'kind' => 1,
                        'content' => 'Nostr is so dead, skip this untagged rant.',
                        'tags' => [],
                        'sig' => str_repeat('d', 128),
                    ],
                ],
            ]),
        ]);

        $run = app(NostrFeedSyncService::class)->sync('bitcoin');
        $this->assertSame('succeeded', $run->status);
        $this->assertDatabaseHas('nostr_notes', [
            'coin_slug' => 'bitcoin',
            'event_id' => str_repeat('a', 64),
        ]);
        $this->assertDatabaseMissing('nostr_notes', [
            'event_id' => str_repeat('c', 64),
        ]);

        Queue::fake();

        Livewire::test(CoinShow::class, ['coin' => Coin::query()->where('slug', 'bitcoin')->firstOrFail()])
            ->assertSee('Community')
            ->assertSee('Bitcoin is looking strong')
            ->assertSee('Rigly')
            ->assertDontSee('Nostr is so dead')
            ->assertSee('https://primal.net/e/' . str_repeat('a', 64), false)
            ->assertSee('View opens the note on Primal');
    }

    public function test_nostr_sync_keeps_lead_topic_and_rejects_late_mentions(): void
    {
        config([
            'nostr.backends' => [
                ['driver' => 'divine', 'base_url' => 'https://gateway.divine.video'],
            ],
            'nostr.authors' => [],
            'nostr.topic_lead_chars' => 160,
            'nostr.feeds' => [
                'bitcoin' => [
                    'authors' => [[
                        'npub' => 'npub1t6el40knsq8hmrpr0m6tt3t0tr4pdeyhlt2qelwhgtwawddqx0xsv03scu',
                        'label' => 'Rigly',
                    ]],
                    'hashtags' => ['btc', 'bitcoin'],
                ],
            ],
        ]);

        $pubkey = '5eb3fabed3800f7d8c237ef4b5c56f58ea16e497fad40cfdd742ddd735a033cd';

        Http::fake([
            'gateway.divine.video/query*' => Http::response([
                'events' => [
                    [
                        'id' => str_repeat('1', 64),
                        'pubkey' => $pubkey,
                        'created_at' => now()->timestamp,
                        'kind' => 1,
                        'content' => 'IMHO Bitcoin is fine. Just the free market learning what works.',
                        'tags' => [],
                        'sig' => str_repeat('2', 128),
                    ],
                    [
                        'id' => str_repeat('3', 64),
                        'pubkey' => $pubkey,
                        'created_at' => now()->timestamp,
                        'kind' => 1,
                        'content' => 'Nostr is so dead, it is hardly worth the effort in development. Sorry. We used Nostr in its heyday to launch the block party, and we still have a core audience here, so we stay. But the network effect is not here. As a small startup, it does not make sense to allocate resources in this way. We have a functional marketplace. We were Nostr\'s first Bitcoin mining block party.',
                        'tags' => [],
                        'sig' => str_repeat('4', 128),
                    ],
                    [
                        'id' => str_repeat('5', 64),
                        'pubkey' => $pubkey,
                        'created_at' => now()->timestamp,
                        'kind' => 1,
                        'content' => 'nope, taking a break from podcasts/social',
                        'tags' => [],
                        'sig' => str_repeat('6', 128),
                    ],
                ],
            ]),
        ]);

        $run = app(NostrFeedSyncService::class)->sync('bitcoin');
        $this->assertSame('succeeded', $run->status);
        $this->assertDatabaseHas('nostr_notes', [
            'event_id' => str_repeat('1', 64),
        ]);
        $this->assertDatabaseMissing('nostr_notes', [
            'event_id' => str_repeat('3', 64),
        ]);
        $this->assertDatabaseMissing('nostr_notes', [
            'event_id' => str_repeat('5', 64),
        ]);
    }

    public function test_nostr_feeds_cover_afmc10_coins(): void
    {
        $afmc10 = config('marketdata.afmc10', []);
        $feeds = array_keys(config('nostr.feeds', []));
        $authors = config('nostr.authors', []);

        $this->assertNotEmpty($afmc10);
        foreach ($afmc10 as $slug) {
            $this->assertContains($slug, $feeds);
            $this->assertNotEmpty(config("nostr.feeds.{$slug}.hashtags"));
        }

        $labels = array_column($authors, 'label');
        foreach (['Gigi', 'Lyn Alden', 'NVK', 'Marty Bent', 'Walker', 'ODELL'] as $label) {
            $this->assertContains($label, $labels);
        }
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

    /**
     * @param  list<string>  $platformIds
     */
    private function coinOnNetworks(string $slug, int $rank, array $platformIds): Coin
    {
        $coin = Coin::query()->create([
            'slug' => $slug,
            'symbol' => strtoupper($slug),
            'name' => ucfirst($slug),
            'rank' => $rank,
            'price' => 1,
            'market_cap' => 1_000_000 - $rank,
            'percent_change_24h' => 1.0,
        ]);

        foreach ($platformIds as $platformId) {
            CoinPlatform::query()->create([
                'coin_id' => $coin->id,
                'platform_id' => $platformId,
            ]);
        }

        return $coin;
    }
}
