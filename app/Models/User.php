<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'price_alerts_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Mirrors the column default. `create()` does not read defaults back, so without this a
     * freshly registered user carries null here and the watchlist's bool property rejects it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price_alerts_enabled' => true,
    ];

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    public function watchedCoins(): BelongsToMany
    {
        return $this->belongsToMany(Coin::class, 'watchlist_items')
            ->withTimestamps()
            ->orderBy('rank');
    }

    public function watches(Coin $coin): bool
    {
        return $this->watchlistItems()->where('coin_id', $coin->id)->exists();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'price_alerts_enabled' => 'boolean',
            'watchlist_recap_sent_on' => 'date',
            'watchlist_weekly_recap_sent_on' => 'date',
        ];
    }
}
