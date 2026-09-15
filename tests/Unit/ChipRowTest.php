<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ChipRow;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Both filter rows on the site (networks on Markets, chains on DexScan) split their catalog
 * with this, so the rule lives in one place: the visible chips, the rest behind More, and the
 * selected item always among the visible ones.
 */
class ChipRowTest extends TestCase
{
    public function test_it_shows_the_head_of_the_catalog_and_hides_the_tail(): void
    {
        $split = ChipRow::split($this->catalog(), 'all', 3);

        $this->assertSame(['solana', 'ethereum', 'base'], $split['shown']->pluck('id')->all());
        $this->assertSame(['ink', 'wax'], $split['rest']->pluck('id')->all());
    }

    /**
     * A chain picked from the More menu has to appear in the row, or the filter reads as
     * untouched while the list below it is filtered.
     */
    public function test_a_selection_from_the_menu_takes_the_last_visible_chip(): void
    {
        $split = ChipRow::split($this->catalog(), 'wax', 3);

        $this->assertSame(['solana', 'ethereum', 'wax'], $split['shown']->pluck('id')->all());
        $this->assertSame(['base', 'ink'], $split['rest']->pluck('id')->all());
    }

    public function test_a_selection_already_visible_moves_nothing(): void
    {
        $split = ChipRow::split($this->catalog(), 'ethereum', 3);

        $this->assertSame(['solana', 'ethereum', 'base'], $split['shown']->pluck('id')->all());
    }

    public function test_it_never_shows_fewer_than_one_chip(): void
    {
        $split = ChipRow::split($this->catalog(), 'all', 0);

        $this->assertCount(1, $split['shown']);
        $this->assertCount(4, $split['rest']);
    }

    public function test_a_short_catalog_leaves_nothing_behind_the_menu(): void
    {
        $split = ChipRow::split($this->catalog()->take(2), 'all', 4);

        $this->assertCount(2, $split['shown']);
        $this->assertTrue($split['rest']->isEmpty());
    }

    /**
     * @return Collection<int, array{id: string}>
     */
    private function catalog(): Collection
    {
        return collect([
            ['id' => 'solana'],
            ['id' => 'ethereum'],
            ['id' => 'base'],
            ['id' => 'ink'],
            ['id' => 'wax'],
        ]);
    }
}
