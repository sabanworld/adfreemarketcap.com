<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncCoinInsights;
use App\Jobs\SyncCurrencyRates;
use App\Jobs\SyncDexPairs;
use App\Jobs\SyncGlobalData;
use App\Jobs\SyncHotCoinTickers;
use App\Jobs\SyncMarketData;
use App\Jobs\SyncTopCoinTickers;
use App\Models\Coin;
use App\Services\Currency\CurrencyRateSyncService;
use App\Services\MarketData\CoinInsightSyncService;
use App\Services\MarketData\CoinTickerSyncService;
use App\Services\MarketData\DexSyncService;
use App\Services\MarketData\MarketSyncService;
use Illuminate\Console\Command;

class SyncMarketDataCommand extends Command
{
    protected $signature = 'marketdata:sync
                            {--queue : Dispatch jobs to the queue instead of running inline}
                            {--dex : Also sync DexScan pairs}
                            {--only-dex : Sync DexScan pairs only}
                            {--insights : Also sync coin insights (treasury / market cycles)}
                            {--only-insights : Sync coin insights only}
                            {--tickers : Also sync exchange tickers (hot list, or top-N when configured)}
                            {--only-tickers : Sync exchange tickers only}
                            {--currencies : Also sync display currency rates}
                            {--only-currencies : Sync display currency rates only}
                            {--coin= : Sync tickers for a single coin slug (with --only-tickers)}';

    protected $description = 'Sync market rankings, global stats, DexScan pairs, tickers, currency rates, and coin insights';

    public function handle(
        MarketSyncService $sync,
        DexSyncService $dexSync,
        CoinInsightSyncService $insightSync,
        CoinTickerSyncService $tickerSync,
        CurrencyRateSyncService $currencySync,
    ): int {
        $onlyDex = (bool) $this->option('only-dex');
        $onlyInsights = (bool) $this->option('only-insights');
        $onlyTickers = (bool) $this->option('only-tickers');
        $onlyCurrencies = (bool) $this->option('only-currencies');
        $includeDex = $onlyDex || (bool) $this->option('dex');
        $includeInsights = $onlyInsights || (bool) $this->option('insights');
        $includeTickers = $onlyTickers || (bool) $this->option('tickers');
        $includeCurrencies = $onlyCurrencies || (bool) $this->option('currencies');
        $onlySomething = $onlyDex || $onlyInsights || $onlyTickers || $onlyCurrencies;

        if ($this->option('queue')) {
            if (! $onlySomething) {
                SyncMarketData::dispatch();
                SyncGlobalData::dispatch();
            }

            if ($includeDex) {
                SyncDexPairs::dispatch();
            }

            if ($includeTickers) {
                SyncHotCoinTickers::dispatch();

                if ((int) config('marketdata.sync.tickers_top_coins', 0) > 0) {
                    SyncTopCoinTickers::dispatch();
                }
            }

            if ($includeCurrencies) {
                SyncCurrencyRates::dispatch();
            }

            if ($includeInsights) {
                SyncCoinInsights::dispatch();
            }

            $this->info('Market data sync jobs dispatched.');

            return self::SUCCESS;
        }

        if (! $onlySomething) {
            $markets = $sync->syncMarkets();
            $global = $sync->syncGlobal();

            $this->info("Markets: {$markets->status} ({$markets->records_processed}) via {$markets->provider}");
            $this->info("Global: {$global->status} via {$global->provider}");
        }

        if ($includeDex) {
            $dex = $dexSync->syncPairs();
            $this->info("Dex pairs: {$dex->status} ({$dex->records_processed}) via {$dex->provider}");
        }

        if ($includeTickers) {
            $slug = $this->option('coin');
            if (is_string($slug) && $slug !== '') {
                $coin = Coin::query()->where('slug', $slug)->firstOrFail();
                $tickers = $tickerSync->syncCoin($coin);
                $this->info("Tickers: {$tickers->status} ({$tickers->records_processed}) via {$tickers->provider}");
            } else {
                $hot = $tickerSync->syncHotCoins();
                $this->info("Hot tickers: {$hot->status} ({$hot->records_processed}) via {$hot->provider}");

                if ((int) config('marketdata.sync.tickers_top_coins', 0) > 0) {
                    $tickers = $tickerSync->syncTopCoins();
                    $this->info("Top tickers: {$tickers->status} ({$tickers->records_processed}) via {$tickers->provider}");
                }
            }
        }

        if ($includeCurrencies) {
            $currencies = $currencySync->syncRates();
            $this->info("Currency rates: {$currencies->status} ({$currencies->records_processed}) via {$currencies->provider}");
        }

        if ($includeInsights) {
            $insights = $insightSync->syncBitcoinInsights();
            $this->info("Insights: {$insights->status} ({$insights->records_processed}) via {$insights->provider}");
        }

        return self::SUCCESS;
    }
}
