<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Watchlist price emails
    |--------------------------------------------------------------------------
    |
    | Move emails use the stored 1h, 24h, and 7d percentages. The first time a
    | saved coin is seen, that reading is stored and no email goes out, so a
    | deploy does not mail the move already on the board. After that, a coin
    | emails when it reaches a new mark: 5%, then every 5% (10, 15, 20, ...).
    | One email per coin per window per cooldown, naming the highest mark
    | reached, so a jump from 4% to 16% is one note for 15% rather than three.
    | The daily recap lists every saved coin, including ones that did not move.
    |
    */

    'enabled' => (bool) env('WATCHLIST_ALERTS_ENABLED', true),

    'first_percent' => 5,

    'step_percent' => 5,

    'cooldown_minutes' => 60,

    'move_interval_minutes' => (int) env('WATCHLIST_ALERTS_INTERVAL', 10),

    // App timezone (UTC). 06:00 UTC is morning in the Netherlands.
    'recap_time' => env('WATCHLIST_RECAP_TIME', '06:00'),

    'windows' => [
        '1h' => 'percent_change_1h',
        '24h' => 'percent_change_24h',
        '7d' => 'percent_change_7d',
    ],

];
