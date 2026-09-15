<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ProviderCallStats;
use App\Models\Admin;
use App\Services\MarketData\GeckoTerminalProvider;
use App\Services\MarketData\ProviderCallCounter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderCallStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        config(['admin.mfa_required' => false]);
        Http::preventStrayRequests();
    }

    public function test_a_geckoterminal_request_increments_this_hour(): void
    {
        config([
            'marketdata.geckoterminal.base_url' => 'https://api.geckoterminal.com/api/v2',
            'marketdata.geckoterminal.api_key' => '',
            'marketdata.geckoterminal.retry_times' => 1,
            'marketdata.geckoterminal.retry_sleep_ms' => 0,
        ]);

        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response([
                'data' => [],
                'included' => [],
            ]),
        ]);

        app(GeckoTerminalProvider::class)->fetchTrendingPools();

        $row = app(ProviderCallCounter::class)->summaries()->first();

        $this->assertSame(ProviderCallCounter::GECKOTERMINAL, $row?->provider);
        $this->assertSame(1, $row?->hour);
        $this->assertSame(1, $row?->day);
        $this->assertSame(1, $row?->month);
    }

    public function test_the_artisan_command_prints_counts(): void
    {
        app(ProviderCallCounter::class)->record(ProviderCallCounter::COINGECKO);

        $this->artisan('marketdata:call-stats')
            ->expectsOutputToContain(__('admin.provider_call_stats.quota_heading'))
            ->expectsOutputToContain('coingecko')
            ->assertSuccessful();
    }

    public function test_admins_can_open_the_api_calls_page(): void
    {
        app(ProviderCallCounter::class)->record(ProviderCallCounter::COINGECKO);

        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ProviderCallStats::class)
            ->assertOk()
            ->assertSee('coingecko')
            ->assertSee(__('admin.provider_call_stats.this_hour'));
    }

    public function test_guests_cannot_open_the_api_calls_page(): void
    {
        $this->get('/admin/provider-call-stats')->assertRedirect();
    }
}
