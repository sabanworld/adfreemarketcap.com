<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Nostr community feeds
    |--------------------------------------------------------------------------
    |
    | Server-side only. The visitor browser never contacts relays. Notes must
    | come from a curated author and be on-topic for that coin: a matching
    | #hashtag / `t` tag, or a whole-word topic term (btc, eth, …) near the
    | start of the note. Passing mentions late in an off-topic rant are dropped.
    |
    | Backends are tried in order. Divine is an HTTP gateway over public relays.
    |
    */

    'backends' => [
        [
            'driver' => 'divine',
            'base_url' => env('NOSTR_DIVINE_BASE_URL', 'https://gateway.divine.video'),
        ],
        [
            'driver' => 'nostr_band',
            'base_url' => env('NOSTR_API_BASE_URL', 'https://api.nostr.band'),
        ],
    ],

    'divine_base_url' => env('NOSTR_DIVINE_BASE_URL', 'https://gateway.divine.video'),

    'api_base_url' => env('NOSTR_API_BASE_URL', 'https://api.nostr.band'),

    'http_timeout' => (int) env('NOSTR_HTTP_TIMEOUT', 12),

    'http_connect_timeout' => (int) env('NOSTR_HTTP_CONNECT_TIMEOUT', 5),

    'event_link_base' => env('NOSTR_EVENT_LINK_BASE', 'https://primal.net/e'),

    'retention_days' => (int) env('NOSTR_RETENTION_DAYS', 90),

    // Drop notes older than this even if they match topic terms.
    'max_age_days' => (int) env('NOSTR_MAX_AGE_DAYS', 90),

    // Fetch a wider window per author so topic filtering still leaves notes.
    'notes_per_author' => (int) env('NOSTR_NOTES_PER_AUTHOR', 40),

    // Whole-word topic terms (btc, eth, …) must appear in this many leading
    // characters unless the note already has a matching #hashtag / `t` tag.
    'topic_lead_chars' => (int) env('NOSTR_TOPIC_LEAD_CHARS', 160),

    'display_limit' => (int) env('NOSTR_DISPLAY_LIMIT', 8),

    'stale_minutes' => (int) env('NOSTR_STALE_MINUTES', 45),

    /*
    | Curated authors shared by every AFMC10 feed unless a feed overrides
    | `authors`. Labels are display fallbacks when the gateway omits a name.
    | Prefer NIP-05 verified Bitcoin voices (see primal.net / own domains).
    |
    */

    'authors' => [
        [
            'npub' => 'npub1hxjnw53mhghumt590kgd3fmqme8jzwwflyxesmm50nnapmqdzu7swqagw3',
            'label' => 'nobsbitcoin',
        ],
        [
            'npub' => 'npub1qny3tkh0acurzla8x3zy4nhrjz5zd8l9sy9jys09umwng00manysew95gx',
            'label' => 'ODELL',
        ],
        [
            'npub' => 'npub1s05p3ha7en49dv8429tkk07nnfa9pcwczkf5x5qrdraqshxdje9sq6eyhe',
            'label' => 'Jeff Booth',
        ],
        [
            'npub' => 'npub1dergggklka99wwrs92yz8wdjs952h2ux2ha2ed598ngwu9w7a6fsh9xzpc',
            'label' => 'Gigi',
        ],
        [
            'npub' => 'npub1a2cww4kn9wqte4ry70vyfwqyqvpswksna27rtxd8vty6c74era8sdcw83a',
            'label' => 'Lyn Alden',
        ],
        [
            'npub' => 'npub1az9xj85cmxv8e9j9y80lvqp97crsqdu2fpu3srwthd99qfu9qsgstam8y8',
            'label' => 'NVK',
        ],
        [
            'npub' => 'npub1guh5grefa7vkay4ps6udxg8lrqxg2kgr3qh9n4gduxut64nfxq0q9y6hjy',
            'label' => 'Marty Bent',
        ],
        [
            'npub' => 'npub1s5yq6wadwrxde4lhfs56gn64hwzuhnfa6r9mj476r5s4hkunzgzqrs6q7z',
            'label' => 'Preston Pysh',
        ],
        [
            'npub' => 'npub1j8y6tcdfw3q3f3h794s6un0gyc5742s0k5h5s2yqj0r70cpklqeqjavrvg',
            'label' => 'Uncle Rockstar',
        ],
        [
            'npub' => 'npub1cj8znuztfqkvq89pl8hceph0svvvqk0qay6nydgk9uyq7fhpfsgsqwrz4u',
            'label' => 'Walker',
        ],
        [
            'npub' => 'npub1qg8j6gdwpxlntlxlkew7eu283wzx7hmj32esch42hntdpqdgrslqv024kw',
            'label' => 'Adam Back',
        ],
        [
            'npub' => 'npub1j4u5gjzjyggr3h96x3gjy4akdgwxuk7mgvumv72gym2qyje7fn5s47d6x9',
            'label' => 'Bitstein',
        ],
        [
            'npub' => 'npub1t6el40knsq8hmrpr0m6tt3t0tr4pdeyhlt2qelwhgtwawddqx0xsv03scu',
            'label' => 'Rigly',
        ],
    ],

    /*
    | One feed per AFMC10 coin slug. `hashtags` doubles as topic terms for the
    | lead-text match (#tag / `t` tag still always qualifies).
    |
    */

    'feeds' => [
        'bitcoin' => [
            'hashtags' => ['btc', 'bitcoin'],
        ],
        'ethereum' => [
            'hashtags' => ['eth', 'ethereum'],
        ],
        'dogecoin' => [
            'hashtags' => ['doge', 'dogecoin'],
        ],
        'litecoin' => [
            'hashtags' => ['ltc', 'litecoin'],
        ],
        'bitcoin-cash' => [
            'hashtags' => ['bch', 'bitcoincash'],
        ],
        'ripple' => [
            'hashtags' => ['xrp', 'ripple'],
        ],
        'binancecoin' => [
            'hashtags' => ['bnb', 'binance'],
        ],
        'hedera-hashgraph' => [
            'hashtags' => ['hbar', 'hedera'],
        ],
        'near' => [
            'hashtags' => ['near', 'nearprotocol'],
        ],
        'sui' => [
            'hashtags' => ['sui'],
        ],
    ],

];
