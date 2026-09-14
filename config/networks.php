<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Network catalog for Markets filters
    |--------------------------------------------------------------------------
    |
    | platform_id values match CoinGecko asset platform ids on /coins/list.
    | pinned appear as pills; the rest are available under More.
    |
    */

    'pinned' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MARKETDATA_NETWORK_PINNED', 'ethereum,binance-smart-chain,solana,base')),
    ))),

    'catalog' => [
        'ethereum' => [
            'label' => 'Ethereum',
            'icon' => 'token',
        ],
        'binance-smart-chain' => [
            'label' => 'BSC',
            'icon' => 'token',
        ],
        'solana' => [
            'label' => 'Solana',
            'icon' => 'token',
        ],
        'base' => [
            'label' => 'Base',
            'icon' => 'token',
        ],
        'arbitrum-one' => [
            'label' => 'Arbitrum',
            'icon' => 'token',
        ],
        'optimistic-ethereum' => [
            'label' => 'Optimism',
            'icon' => 'token',
        ],
        'polygon-pos' => [
            'label' => 'Polygon',
            'icon' => 'token',
        ],
        'avalanche' => [
            'label' => 'Avalanche',
            'icon' => 'token',
        ],
        'sui' => [
            'label' => 'Sui',
            'icon' => 'token',
        ],
        'ton' => [
            'label' => 'TON',
            'icon' => 'token',
        ],
        'tron' => [
            'label' => 'TRON',
            'icon' => 'token',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden platforms
    |--------------------------------------------------------------------------
    |
    | Never show these in the More list even if coins reference them.
    |
    */

    'hidden' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MARKETDATA_NETWORK_HIDDEN', '')),
    ))),

];
