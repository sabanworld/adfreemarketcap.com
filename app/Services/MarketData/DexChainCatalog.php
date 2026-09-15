<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\DexPair;
use App\Support\ChipRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The chains DexScan can filter by, derived from the pairs we actually store.
 *
 * A chain reaches the filter only when a pair on it is in the table, and each one carries the
 * number of pairs behind it, so a reader can see the size of a filter before choosing it. The
 * chain column holds the name we display, so id and label are the same string here.
 */
class DexChainCatalog
{
    /** Versioned, because the old key under this name cached bare chain names. */
    public const CACHE_KEY = 'dexscan.chains.counted';

    public const CACHE_SECONDS = 60;

    /**
     * @return list<array{id: string, label: string, count: int}>
     */
    public function available(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            return DexPair::query()
                ->groupBy('chain')
                ->selectRaw('chain as chain, count(*) as pairs')
                ->orderByDesc('pairs')
                ->orderBy('chain')
                ->get()
                ->reject(fn ($row): bool => blank($row->chain))
                ->map(fn ($row): array => [
                    'id' => (string) $row->chain,
                    'label' => (string) $row->chain,
                    'count' => (int) $row->pairs,
                ])
                ->values()
                ->all();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function isValidChain(string $chain): bool
    {
        if ($chain === 'all') {
            return true;
        }

        return collect($this->available())->contains(fn (array $row): bool => $row['id'] === $chain);
    }

    /**
     * @return array{shown: Collection<int, array{id: string, label: string, count: int}>, rest: Collection<int, array{id: string, label: string, count: int}>}
     */
    public function chipRow(string $selected = 'all', int $visible = 4): array
    {
        return ChipRow::split(collect($this->available()), $selected, $visible);
    }
}
