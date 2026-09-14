<?php

declare(strict_types=1);

namespace App\Services\Watchlist;

use App\Models\Coin;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Support\Collection;

final class WatchlistService
{
    public function watchedCoinIds(User $user): Collection
    {
        return WatchlistItem::query()
            ->where('user_id', $user->id)
            ->pluck('coin_id');
    }

    public function hasAny(User $user): bool
    {
        return WatchlistItem::query()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function isWatched(User $user, Coin $coin): bool
    {
        return WatchlistItem::query()
            ->where('user_id', $user->id)
            ->where('coin_id', $coin->id)
            ->exists();
    }

    public function toggle(User $user, Coin $coin): bool
    {
        $existing = WatchlistItem::query()
            ->where('user_id', $user->id)
            ->where('coin_id', $coin->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        WatchlistItem::query()->create([
            'user_id' => $user->id,
            'coin_id' => $coin->id,
        ]);

        return true;
    }

    /**
     * @return Collection<int, Coin>
     */
    public function coinsFor(User $user): Collection
    {
        return Coin::query()
            ->select(Coin::LIST_COLUMNS)
            ->whereIn('id', $this->watchedCoinIds($user))
            ->orderBy('rank')
            ->get();
    }
}
