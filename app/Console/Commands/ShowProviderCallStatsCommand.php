<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MarketData\DTOs\ProviderCallSummary;
use App\Services\MarketData\ProviderCallCounter;
use Illuminate\Console\Command;

class ShowProviderCallStatsCommand extends Command
{
    protected $signature = 'marketdata:call-stats';

    protected $description = 'Show outbound market-data API call counts for this hour, day, and month';

    public function handle(ProviderCallCounter $counter): int
    {
        $quota = $counter->combined(ProviderCallCounter::COINGECKO_QUOTA);

        $this->info(__('admin.provider_call_stats.quota_heading'));
        $this->table(
            [
                __('admin.provider_call_stats.this_hour'),
                __('admin.provider_call_stats.today'),
                __('admin.provider_call_stats.this_month'),
            ],
            [[$quota->hour, $quota->day, $quota->month]],
        );

        $rows = $counter->summaries();
        if ($rows->isEmpty()) {
            $this->comment(__('admin.provider_call_stats.empty'));

            return self::SUCCESS;
        }

        $this->table(
            [
                __('admin.provider_call_stats.provider'),
                __('admin.provider_call_stats.this_hour'),
                __('admin.provider_call_stats.today'),
                __('admin.provider_call_stats.this_month'),
            ],
            $rows->map(fn (ProviderCallSummary $row): array => [
                $row->provider,
                $row->hour,
                $row->day,
                $row->month,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
