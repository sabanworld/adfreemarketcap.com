<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | ChangeNOW exchange widget
    |--------------------------------------------------------------------------
    |
    | An affiliate swap embed on the home page and every coin page. Loading it
    | sends the visitor's browser to changenow.io (iframe + connector script),
    | so it stays behind the same consent gate as Google Ads: nothing is
    | requested until Accept, and Reject removes the embed. Turning this on is
    | a policy change. See docs/privacy-and-legal.md.
    |
    | link_id is the ChangeNOW partner id. Without it the widget does not
    | render, so a half-configured environment cannot ship an untracked embed.
    |
    */

    'enabled' => (bool) env('EXCHANGE_WIDGET_ENABLED', true),

    'link_id' => env('EXCHANGE_WIDGET_LINK_ID', '2511974805bd4d'),

    'widget_base_url' => env(
        'EXCHANGE_WIDGET_BASE_URL',
        'https://changenow.io/embeds/exchange-widget/v2/widget.html',
    ),

    'connector_script_url' => env(
        'EXCHANGE_WIDGET_CONNECTOR_URL',
        'https://changenow.io/embeds/exchange-widget/v2/stepper-connector.js',
    ),

    /*
    | Default pair and presentation. Coin pages override `from` with the coin's
    | ticker when ChangeNOW is likely to recognise it; `to` flips to btc when
    | the page is already eth.
    */

    'defaults' => [
        'from' => 'btc',
        'to' => 'eth',
        'amount' => '0.01',
        'horizontal' => true,
        'faq' => true,
        'locales' => true,
        'logo' => false,
        'to_the_moon' => false,
        'is_fiat' => false,
        'lang' => 'en-US',
        // ChangeNOW paints its own chrome inside the iframe. Primary follows our
        // amber accent so the Exchange button sits with the rest of the page.
        // Pure black / white canvases so ChangeNOW's rounded frame does not leave
        // light wedges in the corners on a dark page (and the reverse in light).
        'primary_color' => 'FF7A00',
        'background_color_light' => 'FFFFFF',
        'background_color_dark' => '000000',
    ],

];
