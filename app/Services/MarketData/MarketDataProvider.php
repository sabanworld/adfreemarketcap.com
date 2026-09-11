<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\CoinDetailData;
use App\Services\MarketData\DTOs\GlobalMarketData;
use App\Services\MarketData\DTOs\MarketCoinData;
use Illuminate\Support\Collection;

interface MarketDataProvider
{
    public function name(): string;

    /**
     * @return Collection<int, MarketCoinData>
     */
    public function fetchMarkets(int $page, int $perPage): Collection;

    public function fetchGlobal(): GlobalMarketData;

    public function fetchCoinDetail(string $externalId): CoinDetailData;
}
