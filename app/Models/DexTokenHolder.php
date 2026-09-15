<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DexTokenHolder extends Model
{
    protected $fillable = [
        'dex_token_id',
        'rank',
        'address',
        'label',
        'amount',
        'percentage',
        'value_usd',
        'explorer_url',
    ];

    public function token(): BelongsTo
    {
        return $this->belongsTo(DexToken::class, 'dex_token_id');
    }

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'percentage' => 'decimal:6',
            'value_usd' => 'decimal:2',
        ];
    }
}
