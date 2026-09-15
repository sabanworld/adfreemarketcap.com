<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DexPair extends Model
{
    protected $fillable = [
        'slug',
        'provider',
        'external_id',
        'pair',
        'base_symbol',
        'quote_symbol',
        'dex',
        'chain',
        'network_id',
        'contract_address',
        'base_token_address',
        'quote_token_address',
        'dex_token_id',
        'audit_status',
        'price',
        'percent_change_24h',
        'liquidity_usd',
        'volume_24h',
        'volume_1h',
        'volume_6h',
        'fdv_usd',
        'market_cap_usd',
        'txns_24h',
        'buys_24h',
        'sells_24h',
        'paired_at',
        'is_trending',
        'rank',
        'synced_at',
        'detail_synced_at',
        'trades_synced_at',
    ];

    public static function makeSlug(string $pair, string $chain, string $dex): string
    {
        return Str::slug($pair . '-' . $chain . '-' . $dex);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(DexToken::class, 'dex_token_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(DexTrade::class);
    }

    public function chartSeries(): MorphMany
    {
        return $this->morphMany(DexChartSeries::class, 'chartable');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('audit_status', 'verified');
    }

    /**
     * "Hide unverified" keeps Markets-listed (verified) and CoinGecko-linked (partial) pairs.
     */
    public function scopeNotUnverified(Builder $query): Builder
    {
        return $query->whereIn('audit_status', ['verified', 'partial']);
    }

    public function scopeOnChain(Builder $query, string $chain): Builder
    {
        return $query->where('chain', $chain);
    }

    public function ageLabel(): string
    {
        if (! $this->paired_at instanceof Carbon) {
            return '—';
        }

        return $this->paired_at->diffForHumans();
    }

    public function poolAddress(): ?string
    {
        return filled($this->contract_address) ? (string) $this->contract_address : null;
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

    protected function casts(): array
    {
        return [
            'price' => 'decimal:18',
            'percent_change_24h' => 'decimal:4',
            'liquidity_usd' => 'decimal:2',
            'volume_24h' => 'decimal:2',
            'volume_1h' => 'decimal:2',
            'volume_6h' => 'decimal:2',
            'fdv_usd' => 'decimal:2',
            'market_cap_usd' => 'decimal:2',
            'txns_24h' => 'integer',
            'buys_24h' => 'integer',
            'sells_24h' => 'integer',
            'paired_at' => 'datetime',
            'is_trending' => 'boolean',
            'rank' => 'integer',
            'synced_at' => 'datetime',
            'detail_synced_at' => 'datetime',
            'trades_synced_at' => 'datetime',
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
