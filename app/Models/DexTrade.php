<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DexTrade extends Model
{
    protected $fillable = [
        'dex_pair_id',
        'dex_token_id',
        'tx_hash',
        'kind',
        'price_usd',
        'volume_usd',
        'from_token_amount',
        'to_token_amount',
        'trader_address',
        'traded_at',
    ];

    public function pair(): BelongsTo
    {
        return $this->belongsTo(DexPair::class, 'dex_pair_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(DexToken::class, 'dex_token_id');
    }

    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:18',
            'volume_usd' => 'decimal:2',
            'traded_at' => 'datetime',
        ];
    }
}
