<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoinTreasurySnapshot extends Model
{
    protected $fillable = [
        'coin_id',
        'total_holdings',
        'total_value_usd',
        'market_cap_dominance',
        'companies_count',
        'provider',
        'synced_at',
    ];

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    public function holders(): HasMany
    {
        return $this->hasMany(CoinTreasuryHolder::class, 'coin_id', 'coin_id')
            ->orderBy('rank');
    }

    protected function casts(): array
    {
        return [
            'total_holdings' => 'decimal:8',
            'total_value_usd' => 'decimal:2',
            'market_cap_dominance' => 'decimal:4',
            'companies_count' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
