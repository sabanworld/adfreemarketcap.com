<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinTreasuryHolder extends Model
{
    protected $fillable = [
        'coin_id',
        'name',
        'symbol',
        'country',
        'total_holdings',
        'total_entry_value_usd',
        'total_current_value_usd',
        'percentage_of_total_supply',
        'rank',
    ];

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    protected function casts(): array
    {
        return [
            'total_holdings' => 'decimal:8',
            'total_entry_value_usd' => 'decimal:2',
            'total_current_value_usd' => 'decimal:2',
            'percentage_of_total_supply' => 'decimal:6',
            'rank' => 'integer',
        ];
    }
}
