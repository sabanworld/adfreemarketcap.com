<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinPlatform extends Model
{
    protected $fillable = [
        'coin_id',
        'platform_id',
        'contract_address',
    ];

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }
}
