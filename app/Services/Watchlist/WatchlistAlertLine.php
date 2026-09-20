<?php

declare(strict_types=1);

namespace App\Services\Watchlist;

final readonly class WatchlistAlertLine
{
    public function __construct(
        public string $window,
        public string $windowLabel,
        public float $percent,
        public int $band,
    ) {}
}
