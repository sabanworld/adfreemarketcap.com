<?php

declare(strict_types=1);

namespace App\Services\Watchlist;

use App\Models\Coin;

final readonly class WatchlistAlertCoin
{
    /**
     * @param  list<WatchlistAlertLine>  $lines
     */
    public function __construct(
        public Coin $coin,
        public array $lines,
    ) {}
}
