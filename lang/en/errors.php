<?php

declare(strict_types=1);

return [
    'back_home' => 'Back to markets',
    'open_dexscan' => 'Open DexScan',
    'try_again' => 'Try again',
    'sign_in' => 'Sign in',

    401 => [
        'title' => 'Sign in required',
        'detail' => 'You need an account for that page. Rankings and coin pages stay open without one.',
    ],
    402 => [
        'title' => 'Payment required',
        'detail' => 'This site does not charge for market data. If you reached this page, the link is wrong.',
    ],
    403 => [
        'title' => 'Not allowed',
        'detail' => 'You do not have access to that page.',
    ],
    404 => [
        'title' => 'Page not found',
        'detail' => 'That address is not on this site. The coin may have been delisted, or the link is wrong.',
    ],
    419 => [
        'title' => 'Page expired',
        'detail' => 'Your form timed out. Go back, refresh the page, and try again.',
    ],
    429 => [
        'title' => 'Too many requests',
        'detail' => 'Slow down for a moment and try again.',
    ],
    500 => [
        'title' => 'Something broke',
        'detail' => 'We hit a problem loading this page. Try again in a minute.',
    ],
    503 => [
        'title' => 'Temporarily down',
        'detail' => 'The site is offline for a short while. Try again shortly.',
    ],
];
