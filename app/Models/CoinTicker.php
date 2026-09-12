<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinTicker extends Model
{
    protected $fillable = [
        'coin_id',
        'provider',
        'exchange_id',
        'exchange_name',
        'base_symbol',
        'target_symbol',
        'pair',
        'price_usd',
        'last_price',
        'volume_24h_usd',
        'volume_share_percent',
        'bid_ask_spread_percent',
        'trust_score',
        'is_anomaly',
        'is_stale',
        'trade_url',
        'rank',
        'last_traded_at',
        'synced_at',
    ];

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    public function trustLabel(): string
    {
        return match ($this->trust_score) {
            'green' => 'High',
            'yellow' => 'Medium',
            'red' => 'Low',
            default => '—',
        };
    }

    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:18',
            'last_price' => 'decimal:18',
            'volume_24h_usd' => 'decimal:2',
            'volume_share_percent' => 'decimal:4',
            'bid_ask_spread_percent' => 'decimal:6',
            'is_anomaly' => 'boolean',
            'is_stale' => 'boolean',
            'rank' => 'integer',
            'last_traded_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
