<?php

declare(strict_types=1);

use Illuminate\Support\Str;

// "The" belongs to the name on the trade register entry, and an imprint has to
// give the registered name exactly, so an environment that drops it gets it back.
$legalName = trim((string) env('COMPANY_LEGAL_NAME', 'The Saban Company B.V.'));

if (! Str::startsWith(Str::lower($legalName), 'the ')) {
    $legalName = 'The ' . $legalName;
}

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

    'legal_name' => $legalName,

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
    | The person behind the product
    |--------------------------------------------------------------------------
    |
    | Product copy names him instead of saying "the creator", so the footer,
    | the pledge, the picks badges, and the Why ad-free page all read as one
    | person talking. Legal pages keep naming the company above, because that
    | is the entity a visitor deals with and complains to.
    |
    */

    'person' => env('COMPANY_PERSON', 'Anees®'),

    /*
    |--------------------------------------------------------------------------
    | Donations
    |--------------------------------------------------------------------------
    |
    | Optional on-chain address shown in the public footer. Leave empty to hide
    | the block. The QR encodes BIP21 bitcoin:<address>.
    |
    */

    'donation' => [
        'btc_address' => env('COMPANY_BTC_DONATION_ADDRESS', 'bc1qqaez603xp44gzl3ds4j4am62yp8sp2lhkz30ah'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy version
    |--------------------------------------------------------------------------
    |
    | Shown as "Last updated" on every legal page. Bump it in the same change
    | as the policy text so visitors can see which version they read.
    |
    */

    'policies_updated_at' => env('COMPANY_POLICIES_UPDATED_AT', '16 September 2026'),

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
        'nostr_note_days' => (int) env('NOSTR_RETENTION_DAYS', 30),
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
            'name' => env('COMPANY_HOSTING_PROVIDER', 'OVH SAS (OVHcloud)'),
            'location' => env('COMPANY_HOSTING_LOCATION', 'Limburg, Germany (eu-west-lim)'),
            // EEA provider and EEA region: no third-country transfer safeguard.
            'transfer' => env('COMPANY_HOSTING_TRANSFER'),
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
            'name' => 'Plausible Insights OÜ, Tartu',
            'location' => 'Estonia, with the figures processed and stored in Germany',
            'transfer' => null,
        ],
        // Rendered only while config('google-ads.enabled') is true, because a
        // build without the tag must not name a company that receives nothing.
        'advertising' => [
            'category' => 'Advert conversion measurement, only after you accept',
            'name' => env('COMPANY_ADVERTISING_PROVIDER', 'Google Ireland Limited, with Google LLC'),
            'location' => env('COMPANY_ADVERTISING_LOCATION', 'Ireland, with processing by Google LLC in the United States'),
            'transfer' => env('COMPANY_ADVERTISING_TRANSFER', 'EU standard contractual clauses and the EU-US Data Privacy Framework'),
        ],
        // Rendered only while the ChangeNOW exchange widget ships.
        'exchange' => [
            'category' => 'Non-custodial swap widget, only after you accept',
            'name' => env('COMPANY_EXCHANGE_PROVIDER', 'ChangeNOW (ChangeNOW Ltd / Change Group)'),
            'location' => env('COMPANY_EXCHANGE_LOCATION', 'Outside the EEA; your browser contacts changenow.io directly once you accept'),
            'transfer' => env('COMPANY_EXCHANGE_TRANSFER', 'Your browser sends the request straight to ChangeNOW after you accept; see their privacy policy for how they handle it'),
        ],
        'error_monitoring' => [
            'category' => 'Application error reporting',
            'name' => env('COMPANY_ERROR_MONITORING_PROVIDER', 'Functional Software, Inc. (Sentry)'),
            'location' => env('COMPANY_ERROR_MONITORING_LOCATION', 'United States, with optional EU data residency depending on project settings'),
            'transfer' => env('COMPANY_ERROR_MONITORING_TRANSFER', 'EU standard contractual clauses and the EU-US Data Privacy Framework'),
        ],
        'nostr_indexer' => [
            'category' => 'Public Nostr note indexing for community remarks',
            'name' => env('COMPANY_NOSTR_INDEXER', 'Divine Nostr gateway (gateway.divine.video), with Nostr.Band as fallback'),
            'location' => env('COMPANY_NOSTR_INDEXER_LOCATION', 'Outside the EEA (public notes only; no visitor personal data is sent)'),
            'transfer' => env('COMPANY_NOSTR_INDEXER_TRANSFER', 'Public posts only; no visitor personal data is transferred'),
        ],
    ],

];
