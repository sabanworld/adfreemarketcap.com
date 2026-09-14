<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Network catalog for the Markets filter
    |--------------------------------------------------------------------------
    |
    | Keys are CoinGecko asset platform ids, as they arrive on /coins/list. The
    | filter itself is derived from the coin_platforms table, so a chain reaches
    | the chip row only when a ranked coin maps to it. Pinned ids lead the row in
    | the order given here; everything else falls in behind, largest first.
    |
    */

    'pinned' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MARKETDATA_NETWORK_PINNED', 'ethereum,binance-smart-chain,solana,base')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Display names
    |--------------------------------------------------------------------------
    |
    | A platform id is a storage key, not a label: "adi-chain" in a filter chip
    | reads like a bug. Ids are title-cased for display, so this list only needs
    | the ones that do not convert cleanly: acronyms, brand casing, and chains
    | that have since been renamed.
    |
    */

    'names' => [
        'adi-chain' => 'ADI Chain',
        'arbitrum-one' => 'Arbitrum',
        'binance-smart-chain' => 'BSC',
        'binancecoin' => 'BNB Beacon Chain',
        'bittensor-evm' => 'Bittensor EVM',
        'bittorrent' => 'BitTorrent Chain',
        'bob-network' => 'BOB',
        'bsquared-network' => 'B2 Network',
        'cronos-zkevm' => 'Cronos zkEVM',
        'harmony-shard-0' => 'Harmony',
        'hedera-hashgraph' => 'Hedera',
        'huobi-token' => 'HECO',
        'hyperevm' => 'HyperEVM',
        'iota' => 'IOTA',
        'klay-token' => 'Kaia',
        'megaeth' => 'MegaETH',
        'metis-andromeda' => 'Metis',
        'milkomeda-cardano' => 'Milkomeda',
        'near-protocol' => 'NEAR',
        'neon-evm' => 'Neon EVM',
        'okex-chain' => 'OKX Chain',
        'opbnb' => 'opBNB',
        'optimistic-ethereum' => 'Optimism',
        'polygon-pos' => 'Polygon',
        'polygon-zkevm' => 'Polygon zkEVM',
        'sei-v2' => 'Sei',
        'terra-2' => 'Terra',
        'the-open-network' => 'TON',
        'tron' => 'TRON',
        'wemix-network' => 'WEMIX',
        'x-layer' => 'X Layer',
        'xdai' => 'Gnosis',
        'xdc-network' => 'XDC Network',
        'xrp' => 'XRP Ledger',
        'zksync' => 'zkSync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden platforms
    |--------------------------------------------------------------------------
    |
    | Never offer these as a filter, even when coins reference them.
    |
    */

    'hidden' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MARKETDATA_NETWORK_HIDDEN', '')),
    ))),

];
