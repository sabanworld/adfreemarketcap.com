<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchlistPriceAlert extends Model
{
    protected $fillable = [
        'user_id',
        'coin_id',
        'window',
        'direction',
        'notified_percent',
        'emailed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    protected function casts(): array
    {
        return [
            'notified_percent' => 'integer',
            'emailed_at' => 'datetime',
        ];
    }
}
