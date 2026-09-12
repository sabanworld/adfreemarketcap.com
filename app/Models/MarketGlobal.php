<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketGlobal extends Model
{
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
        return static::query()->latest('synced_at')->first();
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
