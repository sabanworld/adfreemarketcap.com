<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\MarketData\DTOs\ProviderCallSummary;
use App\Services\MarketData\ProviderCallCounter;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class ProviderCallStats extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'provider-call-stats';

    protected string $view = 'filament.pages.provider-call-stats';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.provider_call_stats.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.provider_call_stats.title');
    }

    /**
     * @return Collection<int, ProviderCallSummary>
     */
    public function summaries(): Collection
    {
        return app(ProviderCallCounter::class)->summaries();
    }

    public function quota(): ProviderCallSummary
    {
        return app(ProviderCallCounter::class)->combined(ProviderCallCounter::COINGECKO_QUOTA);
    }
}
