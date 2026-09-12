<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Coin;
use App\Models\User;
use App\Services\Watchlist\WatchlistService;
use App\Support\FormRateLimiter;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WatchToggle extends Component
{
    public int $coinId;

    public bool $watched = false;

    public function mount(Coin $coin, WatchlistService $watchlist): void
    {
        $this->coinId = $coin->id;

        $user = Auth::user();
        $this->watched = $user instanceof User
            ? $watchlist->isWatched($user, $coin)
            : false;
    }

    public function toggle(WatchlistService $watchlist): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        FormRateLimiter::ensureIsNotRateLimited('watch_toggle', errorKey: 'watched');
        FormRateLimiter::hit('watch_toggle');

        /** @var User $user */
        $user = Auth::user();
        $coin = Coin::query()->findOrFail($this->coinId);
        $this->watched = $watchlist->toggle($user, $coin);
    }

    public function render()
    {
        return view('livewire.watch-toggle');
    }
}
