<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Picks
    |--------------------------------------------------------------------------
    |
    | Companies named in the "Picks, not ads" section on the home page. Nothing
    | here is paid for, and none of it is commission based. `relationship` drives
    | the badge on the card and the disclosure sentence on the Disclosure of
    | interests page, so both stay in step:
    |
    |   uses    = he uses the product, and has no partnership
    |   partner = he has a strategic partnership with the company
    |
    | "He" is config('company.person'). A note may use the `:person` placeholder
    | and the card fills it in, so the name lives in one place.
    |
    | Adding, removing, or repricing a relationship is a disclosure change: read
    | docs/privacy-and-legal.md and update tests/Feature/LegalPagesTest.php.
    |
    */

    [
        'name' => 'Trezor',
        'kind' => 'Hardware',
        'url' => 'https://trezor.io',
        'note' => 'Hardware wallet for long-term custody, and where :person keeps his own coins.',
        'relationship' => 'uses',
    ],
    [
        'name' => 'ChangeNOW',
        'kind' => 'Swap',
        'url' => 'https://changenow.io',
        'note' => 'Non-custodial swaps with low KYC that still meets AML rules properly, a fast system, and the best conversion prices :person has found so far.',
        'relationship' => 'uses',
    ],
    [
        'name' => 'Rigly',
        'kind' => 'Mining',
        'url' => 'https://rigly.io',
        'note' => 'Bitcoin mining marketplace. :person has a strategic partnership with them, which is why this card carries a partner badge.',
        'relationship' => 'partner',
    ],

];
