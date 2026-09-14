<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class NostrNote extends Model
{
    protected $fillable = [
        'coin_slug',
        'event_id',
        'pubkey',
        'author_name',
        'author_npub',
        'content',
        'published_at',
        'synced_at',
    ];

    /**
     * @return list<self>
     */
    public static function forCoin(string $slug, int $limit = 8): array
    {
        return static::query()
            ->where('coin_slug', $slug)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function relativePublishedAt(): ?string
    {
        if (! $this->published_at instanceof Carbon) {
            return null;
        }

        return $this->published_at->diffForHumans(short: true, parts: 1);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
