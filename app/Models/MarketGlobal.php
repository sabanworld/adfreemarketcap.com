<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MarketGlobal extends Model
{
    public const LATEST_CACHE_KEY = 'market_global.latest';

    public const LATEST_CACHE_SECONDS = 30;

    protected $fillable = [
        'total_market_cap',
        'total_volume_24h',
        'btc_dominance',
        'active_cryptocurrencies',
        'provider',
        'synced_at',
    ];

    public static function latestSnapshot(): ?self
    {
        return Cache::remember(self::LATEST_CACHE_KEY, self::LATEST_CACHE_SECONDS, function () {
            return static::query()->latest('synced_at')->first();
        });
    }

    public static function forgetLatestSnapshotCache(): void
    {
        Cache::forget(self::LATEST_CACHE_KEY);
    }

    protected function casts(): array
    {
        return [
            'total_market_cap' => 'decimal:2',
            'total_volume_24h' => 'decimal:2',
            'btc_dominance' => 'decimal:4',
            'active_cryptocurrencies' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
