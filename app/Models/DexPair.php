<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
        'contract_address',
        'audit_status',
        'price',
        'percent_change_24h',
        'liquidity_usd',
        'volume_24h',
        'txns_24h',
        'paired_at',
        'is_trending',
        'rank',
        'synced_at',
    ];

    public static function makeSlug(string $pair, string $chain, string $dex): string
    {
        return Str::slug($pair . '-' . $chain . '-' . $dex);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('audit_status', 'verified');
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

    protected function casts(): array
    {
        return [
            'price' => 'decimal:18',
            'percent_change_24h' => 'decimal:4',
            'liquidity_usd' => 'decimal:2',
            'volume_24h' => 'decimal:2',
            'txns_24h' => 'integer',
            'paired_at' => 'datetime',
            'is_trending' => 'boolean',
            'rank' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
