<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Operator identity
    |--------------------------------------------------------------------------
    |
    | These values feed the imprint, privacy policy, terms, and complaints
    | pages. The e-Commerce Directive (article 5) requires the legal name,
    | geographic address, trade register number, and an email address to be
    | directly accessible, so keep them accurate.
    |
    */

    'legal_name' => env('COMPANY_LEGAL_NAME', 'Saban Company B.V.'),

    'legal_form' => env('COMPANY_LEGAL_FORM', 'Besloten vennootschap (B.V.)'),

    'kvk' => env('COMPANY_KVK', '91125030'),

    // Dutch BTW-identificatienummer. Required in the imprint once VAT registered.
    'vat' => env('COMPANY_VAT'),

    'address' => [
        'street' => 'Jaap Bijzerweg 19',
        'postal_code' => '3446 CR',
        'city' => 'Woerden',
        'country' => 'the Netherlands',
    ],

    'website' => env('COMPANY_WEBSITE', 'https://the.saban.company/en'),

    'contact_email' => env('COMPANY_CONTACT_EMAIL', 'hello@the.saban.company'),

    // GDPR requests and the DSA point of contact. Falls back to contact_email.
    'privacy_email' => env('COMPANY_PRIVACY_EMAIL', env('COMPANY_CONTACT_EMAIL', 'hello@the.saban.company')),

    'product_name' => env('APP_NAME', 'adfreemarketcap.com'),

    /*
    |--------------------------------------------------------------------------
    | Policy version
    |--------------------------------------------------------------------------
    |
    | Shown as "Last updated" on every legal page. Bump it in the same change
    | as the policy text so visitors can see which version they read.
    |
    */

    'policies_updated_at' => env('COMPANY_POLICIES_UPDATED_AT', '12 September 2026'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Periods promised in the privacy policy. Only claim what the servers and
    | jobs actually do, otherwise the policy is inaccurate.
    |
    */

    'retention' => [
        'account_deletion_days' => (int) env('COMPANY_RETENTION_ACCOUNT_DAYS', 30),
        'server_log_days' => (int) env('COMPANY_RETENTION_LOG_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervisory and consumer bodies
    |--------------------------------------------------------------------------
    |
    | The GDPR requires us to name a supervisory authority, and Dutch consumer
    | rules expect a route to out-of-court help. The EU ODR platform closed on
    | 20 July 2025, so do not link to it.
    |
    */

    'authorities' => [
        'data_protection' => [
            'name' => 'Autoriteit Persoonsgegevens',
            'url' => 'https://www.autoriteitpersoonsgegevens.nl',
        ],
        'consumer' => [
            'name' => 'ACM ConsuWijzer',
            'url' => 'https://www.consuwijzer.nl',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processors and recipients
    |--------------------------------------------------------------------------
    |
    | Rendered as the recipients table in the privacy policy. Leave `name` null
    | while a vendor is undecided so the page names the category only, and fill
    | it in before launch. `transfer` describes the safeguard for any processing
    | outside the EEA.
    |
    */

    'processors' => [
        'hosting' => [
            'category' => 'Hosting, database, and backups',
            'name' => env('COMPANY_HOSTING_PROVIDER', 'DigitalOcean, LLC'),
            'location' => env('COMPANY_HOSTING_LOCATION', 'Amsterdam, the Netherlands (AMS3), with support access from the United States'),
            'transfer' => env('COMPANY_HOSTING_TRANSFER', 'EU standard contractual clauses and the EU-US Data Privacy Framework'),
        ],
        'server_management' => [
            'category' => 'Server provisioning, deployment, and monitoring',
            'name' => env('COMPANY_SERVER_MANAGEMENT_PROVIDER', 'Ploi (WebBuilds B.V.)'),
            'location' => env('COMPANY_SERVER_MANAGEMENT_LOCATION', 'The Netherlands'),
            'transfer' => null,
        ],
        'proxy' => [
            'category' => 'Reverse proxy, DDoS protection, and bot filtering',
            'name' => 'Cloudflare, Inc.',
            'location' => 'European edge locations, with support access from the United States',
            'transfer' => 'EU standard contractual clauses and the EU-US Data Privacy Framework',
        ],
        'mail' => [
            'category' => 'Transactional email (account and support messages)',
            'name' => env('COMPANY_MAIL_PROVIDER'),
            'location' => env('COMPANY_MAIL_LOCATION', 'European Union'),
            'transfer' => null,
        ],
        'analytics' => [
            'category' => 'Visitor statistics, without cookies or stored IP addresses',
            'name' => 'Simple Analytics B.V., Amsterdam',
            'location' => 'The Netherlands, with content delivery from European edge locations',
            'transfer' => null,
        ],
    ],

];
