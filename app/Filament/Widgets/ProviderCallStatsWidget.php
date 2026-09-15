<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\MarketData\ProviderCallCounter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProviderCallStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getHeading(): ?string
    {
        return __('admin.provider_call_stats.quota_heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.provider_call_stats.quota_description');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $quota = app(ProviderCallCounter::class)->combined(ProviderCallCounter::COINGECKO_QUOTA);

        return [
            Stat::make(__('admin.provider_call_stats.this_hour'), number_format($quota->hour)),
            Stat::make(__('admin.provider_call_stats.today'), number_format($quota->day)),
            Stat::make(__('admin.provider_call_stats.this_month'), number_format($quota->month)),
        ];
    }
}
