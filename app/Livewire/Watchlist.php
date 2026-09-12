<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Coin;
use App\Models\User;
use App\Services\Seo\SeoService;
use App\Services\Watchlist\WatchlistService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Watchlist extends Component
{
    public function toggle(int $coinId, WatchlistService $watchlist): void
    {
        /** @var User $user */
        $user = Auth::user();
        $coin = Coin::query()->findOrFail($coinId);
        $watchlist->toggle($user, $coin);
    }

    public function render(WatchlistService $watchlist, SeoService $seo)
    {
        /** @var User $user */
        $user = Auth::user();
        $coins = $watchlist->coinsFor($user);

        $best = $coins->sortByDesc(fn (Coin $coin) => (float) ($coin->percent_change_24h ?? PHP_FLOAT_MIN))->first();
        $worst = $coins->sortBy(fn (Coin $coin) => (float) ($coin->percent_change_24h ?? PHP_FLOAT_MAX))->first();

        $pageSeo = $seo->forStaticPage(
            title: __('seo.watchlist_title', ['site' => config('app.name')]),
            description: __('seo.watchlist_description'),
            canonical: route('watchlist'),
            robots: 'noindex,nofollow',
        );

        return view('livewire.watchlist', [
            'coins' => $coins,
            'best' => $best,
            'worst' => $worst,
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }
}
