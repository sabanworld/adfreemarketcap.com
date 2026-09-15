<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class DexToken extends Model
{
    protected $fillable = [
        'network_id',
        'address',
        'symbol',
        'name',
        'coingecko_coin_id',
        'price',
        'percent_change_24h',
        'fdv_usd',
        'market_cap_usd',
        'liquidity_usd',
        'volume_24h',
        'holders_count',
        'detail_synced_at',
        'trades_synced_at',
        'holders_synced_at',
        'synced_at',
    ];

    public function pairs(): HasMany
    {
        return $this->hasMany(DexPair::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(DexTrade::class);
    }

    public function holders(): HasMany
    {
        return $this->hasMany(DexTokenHolder::class);
    }

    public function chartSeries(): MorphMany
    {
        return $this->morphMany(DexChartSeries::class, 'chartable');
    }

    public function displayName(): string
    {
        return filled($this->name) ? (string) $this->name : (string) $this->symbol;
    }

    public function detailIsStale(?int $minutes = null): bool
    {
        $minutes ??= (int) config('marketdata.sync.dex_detail_stale_minutes', 5);

        return $this->timestampIsStale($this->detail_synced_at, $minutes);
    }

    public function tradesAreStale(?int $minutes = null): bool
    {
        $minutes ??= (int) config('marketdata.sync.dex_trades_stale_minutes', 5);

        return $this->timestampIsStale($this->trades_synced_at, $minutes);
    }

    public function holdersAreStale(?int $minutes = null): bool
    {
        $minutes ??= (int) config('marketdata.sync.dex_holders_stale_minutes', 60);

        return $this->timestampIsStale($this->holders_synced_at, $minutes);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:18',
            'percent_change_24h' => 'decimal:4',
            'fdv_usd' => 'decimal:2',
            'market_cap_usd' => 'decimal:2',
            'liquidity_usd' => 'decimal:2',
            'volume_24h' => 'decimal:2',
            'holders_count' => 'integer',
            'detail_synced_at' => 'datetime',
            'trades_synced_at' => 'datetime',
            'holders_synced_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    private function timestampIsStale(mixed $value, int $minutes): bool
    {
        if (! $value instanceof Carbon) {
            return true;
        }

        return $value->lte(now()->subMinutes(max(1, $minutes)));
    }
}
