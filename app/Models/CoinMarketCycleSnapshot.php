<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinMarketCycleSnapshot extends Model
{
    protected $fillable = [
        'coin_id',
        'price',
        'ma111',
        'ma350x2',
        'ma_gap_percent',
        'pi_cycle_status',
        'last_cross_at',
        'last_halving_at',
        'next_halving_at',
        'days_since_halving',
        'days_until_halving',
        'cycle_progress_percent',
        'halving_epoch',
        'chart_price',
        'chart_ma111',
        'chart_ma350x2',
        'provider',
        'synced_at',
    ];

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'ma111' => 'decimal:8',
            'ma350x2' => 'decimal:8',
            'ma_gap_percent' => 'decimal:4',
            'last_cross_at' => 'datetime',
            'last_halving_at' => 'datetime',
            'next_halving_at' => 'datetime',
            'days_since_halving' => 'integer',
            'days_until_halving' => 'integer',
            'cycle_progress_percent' => 'decimal:4',
            'halving_epoch' => 'integer',
            'chart_price' => 'array',
            'chart_ma111' => 'array',
            'chart_ma350x2' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
