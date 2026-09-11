<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\SyncCoinDetail;
use App\Models\Coin;
use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CoinShow extends Component
{
    public Coin $coin;

    public function mount(Coin $coin): void
    {
        $this->coin = $coin;

        if ($this->coin->detailIsStale()) {
            SyncCoinDetail::dispatch($this->coin->id);
        }
    }

    public function render(SeoService $seo)
    {
        $pageSeo = $seo->forCoin($this->coin);

        return view('livewire.coin-show')
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }
}
