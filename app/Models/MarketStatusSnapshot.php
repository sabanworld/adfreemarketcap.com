<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MarketStatusSnapshot extends Model
{
    public const LATEST_CACHE_KEY = 'market_status.latest';

    public const LATEST_CACHE_SECONDS = 30;

    protected $fillable = [
        'fear_greed_value',
        'fear_greed_classification',
        'afmc10_value',
        'afmc10_change_24h',
        'afmc10_change_7d',
        'afmc10_change_30d',
        'afmc10_change_200d',
        'afmc10_change_1y',
        'afmc10_base_sum',
        'altcoin_season_index',
        'altcoin_season_sample_size',
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
            'fear_greed_value' => 'integer',
            'afmc10_value' => 'decimal:4',
            'afmc10_change_24h' => 'decimal:4',
            'afmc10_change_7d' => 'decimal:4',
            'afmc10_change_30d' => 'decimal:4',
            'afmc10_change_200d' => 'decimal:4',
            'afmc10_change_1y' => 'decimal:4',
            'afmc10_base_sum' => 'decimal:2',
            'altcoin_season_index' => 'decimal:2',
            'altcoin_season_sample_size' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
