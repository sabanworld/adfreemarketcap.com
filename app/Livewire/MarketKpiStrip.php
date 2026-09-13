<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Coin;
use App\Models\MarketGlobal;
use Livewire\Component;

class MarketKpiStrip extends Component
{
    public function render()
    {
        return view('livewire.market-kpi-strip', [
            'global' => MarketGlobal::latestSnapshot(),
            'coinCount' => Coin::rankedCount(),
        ]);
    }
}
