<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\DexPairData;
use Illuminate\Support\Collection;

interface DexDataProvider
{
    public function name(): string;

    /**
     * @return Collection<int, DexPairData>
     */
    public function fetchTrendingPools(int $page = 1): Collection;

    /**
     * @return Collection<int, DexPairData>
     */
    public function fetchNewPools(int $page = 1): Collection;

    /**
     * @return Collection<int, DexPairData>
     */
    public function fetchNetworkPools(string $network, int $page = 1): Collection;
}
