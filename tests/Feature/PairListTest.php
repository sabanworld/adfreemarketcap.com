<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\DexScan;
use App\Models\DexPair;
use App\Services\MarketData\DexChainCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * DexScan's table has nine columns, so on a phone it showed the pair and the price and kept
 * liquidity, age and the quality tier off the right edge: the three facts that decide whether a
 * pool is worth opening. Below 700px the same rows ship as a list that opens in place instead.
 */
class PairListTest extends TestCase
{
    use RefreshDatabase;

    public function test_dexscan_ships_both_the_table_and_the_phone_list(): void
    {
        $this->pair();

        $response = $this->get(route('dexscan'));

        $response->assertOk();
        $response->assertSee('afmc-board__list', false);
        $response->assertSee('afmc-board__table', false);
        $response->assertSee('afmc-list__row', false);
        $response->assertSee('aria-controls="afmc-pair-weth-usdc-ethereum-uniswap-v3"', false);
        $response->assertSee('id="afmc-pair-weth-usdc-ethereum-uniswap-v3"', false);
    }

    /**
     * The row carries what a reader decides on. Liquidity and the quality tier are the two the
     * table pushed off screen, so they sit on the second line rather than inside the panel.
     */
    public function test_a_row_carries_the_facts_that_decide_a_tap(): void
    {
        $pair = $this->pair(['liquidity_usd' => 48_200_000, 'percent_change_24h' => -2.41]);

        $rendered = $this->render();

        $row = Str::between($rendered, 'afmc-list__row', 'afmc-list__panel');

        $this->assertStringContainsString('WETH/USDC', $row);
        $this->assertStringContainsString('Ethereum', $row);
        $this->assertStringContainsString('$3,000', $row);
        $this->assertStringContainsString('$48.20M', $row);
        $this->assertStringContainsString('afmc-risk--established', $row);
        // Direction is never the colour alone: without a caret the sign carries it.
        $this->assertStringContainsString('-2.41%', $row);
        // The pool's name does not fit beside the chain once the price has its characters, so
        // the row carries the chain and the panel carries the pool.
        $this->assertStringNotContainsString('Uniswap v3', $row);
        // The pair page is a button inside the panel: a row that navigates on tap costs the
        // reader their place in the list.
        $this->assertStringContainsString('href="' . route('dexscan.pair', $pair) . '"', $rendered);
        $this->assertStringContainsString('WETH/USDC details', $rendered);
    }

    public function test_the_panel_carries_the_columns_the_row_has_no_room_for(): void
    {
        $this->pair(['volume_24h' => 50_000_000, 'txns_24h' => 1200, 'audit_status' => 'partial']);

        $panel = Str::after($this->render(), 'afmc-list__panel');

        $this->assertStringContainsString('Pool', $panel);
        $this->assertStringContainsString('Uniswap v3', $panel);
        $this->assertStringContainsString('Volume 24h', $panel);
        $this->assertStringContainsString('$50.00M', $panel);
        $this->assertStringContainsString('Txns 24h', $panel);
        $this->assertStringContainsString('1,200', $panel);
        $this->assertStringContainsString('Age', $panel);
        $this->assertStringContainsString('Partial', $panel);
    }

    /**
     * In the table the tier's reason is a title attribute, which a phone cannot hover, so the
     * panel prints the sentence. A tier with no reason attached is a rating without a basis.
     */
    public function test_the_panel_prints_the_reason_behind_the_quality_tier(): void
    {
        $this->pair(['liquidity_usd' => 30_000, 'audit_status' => 'unverified', 'paired_at' => now()->subDay()]);

        $rendered = $this->render();

        $this->assertStringContainsString('afmc-risk--high-risk', $rendered);
        $this->assertStringContainsString('Assume you can lose everything.', $rendered);
    }

    /**
     * Seventeen chains as wrapping pills cost four rows of a phone screen before any data. The
     * filter is the chip row Markets uses: a panning row, the rest behind More, and the chain a
     * reader picked from that menu promoted into the row so the filter never looks untouched.
     */
    public function test_the_chain_filter_is_one_row_with_the_rest_behind_more(): void
    {
        foreach (['Solana', 'Ethereum', 'Base', 'Arbitrum', 'Ink', 'Wax'] as $index => $chain) {
            $this->pair(['slug' => Str::slug($chain), 'chain' => $chain, 'volume_24h' => 1_000_000 * (10 - $index)]);
        }

        $response = $this->get(route('dexscan'));

        $response->assertOk();
        $response->assertSee('All chains', false);
        $response->assertSee('afmc-chip--more', false);
        // The count on a chip is the number of pairs behind the filter, not a decoration.
        $response->assertSee('Search chains', false);
        $response->assertSee('setChain', false);
    }

    public function test_the_chain_filter_promotes_the_chain_a_reader_picked_from_the_menu(): void
    {
        foreach (['Solana', 'Ethereum', 'Base', 'Arbitrum', 'Ink', 'Wax'] as $index => $chain) {
            $this->pair(['slug' => Str::slug($chain), 'chain' => $chain, 'volume_24h' => 1_000_000 * (10 - $index)]);
        }

        $chips = app(DexChainCatalog::class)->chipRow('Wax');

        $this->assertTrue($chips['shown']->contains(fn (array $row): bool => $row['id'] === 'Wax'));
        $this->assertFalse($chips['rest']->contains(fn (array $row): bool => $row['id'] === 'Wax'));
    }

    /**
     * chain arrives from the query string, so a junk value would otherwise render an empty
     * table on a crawlable URL.
     */
    public function test_a_chain_the_catalog_does_not_know_falls_back_to_all(): void
    {
        $this->pair(['chain' => 'Ethereum']);

        Livewire::withQueryParams(['chain' => 'Nonesuch'])
            ->test(DexScan::class)
            ->assertSet('chain', 'all');

        Livewire::test(DexScan::class)
            ->call('setChain', 'Nonesuch')
            ->assertSet('chain', 'all')
            ->call('setChain', 'Ethereum')
            ->assertSet('chain', 'Ethereum');
    }

    public function test_an_empty_result_explains_itself_once_for_both_forms(): void
    {
        $response = $this->get(route('dexscan'));

        $response->assertOk();
        $response->assertSee('No pairs yet', false);
        $response->assertDontSee('afmc-board__list', false);
    }

    private function render(): string
    {
        return Blade::render(
            '<x-afmc.pair-list :pairs="$pairs" />',
            ['pairs' => DexPair::query()->get()],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pair(array $attributes = []): DexPair
    {
        app(DexChainCatalog::class)->forgetCache();

        return DexPair::query()->create($attributes + [
            'slug' => 'weth-usdc-ethereum-uniswap-v3',
            'pair' => 'WETH/USDC',
            'base_symbol' => 'WETH',
            'quote_symbol' => 'USDC',
            'dex' => 'Uniswap v3',
            'chain' => 'Ethereum',
            'audit_status' => 'verified',
            'price' => 3000,
            'percent_change_24h' => 1.5,
            'liquidity_usd' => 10_000_000,
            'volume_24h' => 50_000_000,
            'txns_24h' => 1200,
            'paired_at' => now()->subDays(120),
            'is_trending' => true,
            'synced_at' => now(),
        ]);
    }
}
