<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Splits a filter catalog into the chips a reader sees and the rest behind the More menu.
 *
 * Both filter rows on the site (networks on Markets, chains on DexScan) need the same two
 * rules, and getting one of them wrong is invisible until a reader picks a chain from the menu
 * and the row looks untouched: the selected item is always promoted into the visible chips.
 */
final class ChipRow
{
    /**
     * @template TItem of array{id: string}
     *
     * @param  Collection<int, TItem>  $items
     * @return array{shown: Collection<int, TItem>, rest: Collection<int, TItem>}
     */
    public static function split(Collection $items, string $selected = 'all', int $visible = 4): array
    {
        $visible = max(1, $visible);

        $shown = $items->take($visible);
        $picked = $items->firstWhere('id', $selected);

        if ($picked !== null && ! $shown->contains(fn (array $row): bool => $row['id'] === $selected)) {
            $shown = $items->take($visible - 1)->push($picked);
        }

        $shownIds = $shown->pluck('id')->all();

        return [
            'shown' => $shown->values(),
            'rest' => $items
                ->reject(fn (array $row): bool => in_array($row['id'], $shownIds, true))
                ->values(),
        ];
    }
}
