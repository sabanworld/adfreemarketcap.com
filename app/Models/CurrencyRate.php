<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $fillable = [
        'code',
        'provider',
        'rate_per_usd',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_usd' => 'float',
            'synced_at' => 'datetime',
        ];
    }
}
