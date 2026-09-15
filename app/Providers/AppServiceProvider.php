<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DexPair;
use App\Models\DexToken;
use App\Services\Currency\CurrencyService;
use App\Services\Currency\ExchangeRateProvider;
use App\Services\Currency\MarketDisplayService;
use App\Services\MarketData\CoinGeckoProvider;
use App\Services\MarketData\DexDataProvider;
use App\Services\MarketData\GeckoTerminalProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DexDataProvider::class, GeckoTerminalProvider::class);
        $this->app->bind(ExchangeRateProvider::class, CoinGeckoProvider::class);
        $this->app->singleton(CurrencyService::class);
        $this->app->scoped(MarketDisplayService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'dex_pair' => DexPair::class,
            'dex_token' => DexToken::class,
        ]);
    }
}
