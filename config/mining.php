<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Miners block (home page)
    |--------------------------------------------------------------------------
    |
    | Drives the "For bitcoin miners" section on the home page. The partner here
    | is the same company as the Rigly card in config/picks.php, so the badge and
    | the disclosure sentence have to keep saying "strategic partnership": that is
    | a disclosure claim, not decoration. Read docs/privacy-and-legal.md and keep
    | tests/Feature/MiningBlockTest.php in step when any of it changes.
    |
    | Set MINING_BLOCK_ENABLED=false to hide the section.
    |
    */

    'enabled' => (bool) env('MINING_BLOCK_ENABLED', true),

    /*
    | Coin pages that show the block. Match on slug or symbol, so `bitcoin` and
    | `btc` both work. The home page shows the block regardless of this list.
    */
    'coins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', mb_strtolower((string) env('MINING_COINS', 'bitcoin,btc'))),
    ))),

    'partner' => [
        'name' => env('MINING_PARTNER_NAME', 'Rigly'),
        'product' => env('MINING_PARTNER_PRODUCT', 'Blockparty'),
        'url' => env('MINING_PARTNER_URL', 'https://blockparty.rigly.io'),
    ],

    'min_bid_sats' => (int) env('MINING_MIN_BID_SATS', 1000),

    /*
    | The most recent block the pool found. Only fill this in from a block that
    | can be checked in an explorer, and update `found_at` with it.
    */
    'last_block' => [
        'height' => (int) env('MINING_LAST_BLOCK_HEIGHT', 955703),
        'found_at' => env('MINING_LAST_BLOCK_FOUND_AT', 'June 2026'),
        'hash' => env('MINING_LAST_BLOCK_HASH', '00000000000000000001089806eb0365ac250e44bc0b306ba0339a6b63c63fc5'),
        'explorer' => env('MINING_LAST_BLOCK_EXPLORER', 'https://mempool.space/block'),
    ],

];
