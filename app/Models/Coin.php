<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Coin extends Model
{
    protected $fillable = [
        'slug',
        'symbol',
        'name',
        'image_url',
        'rank',
        'price',
        'percent_change_1h',
        'percent_change_24h',
        'percent_change_7d',
        'market_cap',
        'volume_24h',
        'circulating_supply',
        'sparkline_7d',
        'chart_7d',
        'description',
        'last_provider',
        'market_synced_at',
        'detail_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'price' => 'decimal:10',
            'percent_change_1h' => 'decimal:4',
            'percent_change_24h' => 'decimal:4',
            'percent_change_7d' => 'decimal:4',
            'market_cap' => 'decimal:2',
            'volume_24h' => 'decimal:2',
            'circulating_supply' => 'decimal:4',
            'sparkline_7d' => 'array',
            'chart_7d' => 'array',
            'market_synced_at' => 'datetime',
            'detail_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function providerIds(): HasMany
    {
        return $this->hasMany(CoinProviderId::class);
    }

    public function detailIsStale(?int $hours = null): bool
    {
        $hours ??= (int) config('marketdata.sync.coin_detail_stale_hours', 6);

        if (! $this->detail_synced_at instanceof Carbon) {
            return true;
        }

        return $this->detail_synced_at->lte(now()->subHours($hours));
    }
}
