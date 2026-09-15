<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\CoinPlatform;
use App\Support\ChipRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NetworkCatalogService
{
    public const AVAILABLE_CACHE_KEY = 'networks.available';

    public const AVAILABLE_CACHE_SECONDS = 300;

    /**
     * Networks are derived, never hand-listed: a chain reaches the filter only when a ranked
     * coin maps to it, and each one carries the number of coins behind it so a reader can see
     * the size of a filter before choosing it.
     *
     * @return list<array{id: string, label: string, count: int, pinned: bool}>
     */
    public function availableNetworks(): array
    {
        return Cache::remember(self::AVAILABLE_CACHE_KEY, self::AVAILABLE_CACHE_SECONDS, function (): array {
            /** @var array<string, string> $names */
            $names = config('networks.names', []);
            /** @var list<string> $pinned */
            $pinned = config('networks.pinned', []);
            /** @var list<string> $hidden */
            $hidden = config('networks.hidden', []);

            $counts = CoinPlatform::query()
                ->join('coins', 'coins.id', '=', 'coin_platforms.coin_id')
                ->whereNotNull('coins.rank')
                ->groupBy('coin_platforms.platform_id')
                ->selectRaw('coin_platforms.platform_id as platform_id, count(*) as coins')
                ->pluck('coins', 'platform_id');

            $rows = $counts
                ->reject(fn ($count, $id): bool => ! is_string($id) || in_array($id, $hidden, true))
                ->map(fn ($count, string $id): array => [
                    'id' => $id,
                    // A platform id is a storage key, not a label: "adi-chain" in a filter chip
                    // reads like a bug. Ids that do not title-case cleanly carry an explicit
                    // name in config/networks.php.
                    'label' => (string) ($names[$id] ?? Str::headline($id)),
                    'count' => (int) $count,
                    'pinned' => in_array($id, $pinned, true),
                ]);

            // Pinned chains keep the order the operator gave them; everything else falls in
            // behind, biggest first, so the overflow menu opens on the chains that matter.
            $head = collect($pinned)
                ->map(fn ($id): ?array => $rows->get((string) $id))
                ->filter();

            $tail = $rows
                ->reject(fn (array $row): bool => $row['pinned'])
                ->sortBy([['count', 'desc'], ['id', 'asc']]);

            return $head->concat($tail)->values()->all();
        });
    }

    public function forgetAvailableCache(): void
    {
        Cache::forget(self::AVAILABLE_CACHE_KEY);
    }

    public function isValidNetwork(string $network): bool
    {
        if ($network === 'all') {
            return true;
        }

        return collect($this->availableNetworks())->contains(fn (array $row): bool => $row['id'] === $network);
    }

    public function networkLabel(string $network): ?string
    {
        $row = collect($this->availableNetworks())->firstWhere('id', $network);

        return $row === null ? null : $row['label'];
    }

    /**
     * Split the catalog into the chips a reader sees and the rest behind the More menu. The
     * selected chain is promoted into the chip row when it came from that menu, so the active
     * filter is never hidden behind a button that looks untouched.
     *
     * @return array{shown: Collection<int, array{id: string, label: string, count: int, pinned: bool}>, rest: Collection<int, array{id: string, label: string, count: int, pinned: bool}>}
     */
    public function chipRow(string $selected = 'all', int $visible = 4): array
    {
        return ChipRow::split(collect($this->availableNetworks()), $selected, $visible);
    }
}
