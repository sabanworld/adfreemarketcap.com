@php
    $company = config('company');
    $person = $company['person'];
@endphp

<main data-afmc-page class="afmc-page" style="max-width:56rem">
    <header class="afmc-why__head">
        <span class="afmc-why__eyebrow">{{ __('About') }}</span>
        <h1 style="font:var(--type-h1);margin:0">{{ __('Why ad-free') }}</h1>
        <p class="afmc-why__lead">
            {{ __(':person can\'t stand ad-riddled apps and websites, so he didn\'t build one. Looking up a price is a five second job, and on most crypto sites it costs you a banner, a video, a newsletter box and a consent dialog first. Market data is public information, and reading it should be free and simple.', [
                'person' => $person,
            ]) }}
        </p>
    </header>

    <x-afmc.pledge-band style="margin-top:var(--space-6)" />

    <article class="afmc-legal-prose" style="margin-top:var(--space-8)">
        <h2>{{ __('What we got tired of') }}</h2>
        <p>{{ __('Open a popular price page on your phone and count what happens before you see a number.') }}</p>
        <ul>
            <li>{{ __('A banner up top, another glued to the bottom, and a third sliding in over the chart.') }}</li>
            <li>{{ __('Video that starts talking on a page you opened to read one figure.') }}</li>
            <li>{{ __('The table hopping down the screen as the last ad loads, so you tap the wrong row.') }}</li>
            <li>{{ __('A newsletter box before you have read a sentence.') }}</li>
            <li>{{ __('Rows near the top that are there because somebody paid, in small grey type, or not marked at all.') }}</li>
            <li>{{ __('A consent dialog with one bright accept button and a reject link buried two screens deep.') }}</li>
            <li>{{ __('Megabytes of tracking code wrapped around a few kilobytes of prices.') }}</li>
        </ul>
        <p>{{ __('None of that is for you. It is there because the page has to pay for itself, and your attention is the thing being sold. :person did not want to build that, so he didn\'t.', ['person' => $person]) }}</p>

        <h2>{{ __('Free and simple, in practice') }}</h2>
        <ul>
            <li>{{ __('No account. Rankings, coin pages and DexScan are open to anyone. You only sign in if you want a watchlist.') }}</li>
            <li>{{ __('No paywall and no metered reading. There is no premium tier sitting on a chart or a longer history.') }}</li>
            <li>{{ __('No pop-ups, no interstitials, no autoplay, and nothing sliding in while you read.') }}</li>
            <li>{{ __('No cookie wall. The bar you see once is a notice rather than a request, because nothing we store is for marketing or measurement. It keeps your session, your theme, your currency, and the fact that you already read the notice.') }}</li>
            <li>{{ __('The number you came for is at the top of the page on the first load, and it stays put once the rest arrives.') }}</li>
        </ul>

        <h2>{{ __('What we do instead') }}</h2>
        <p>{{ __('Dropping the ads is the easy part. Keeping the page honest and light is the actual work.') }}</p>
        <ul>
            <li>{{ __('Zero ad slots. None sold, none planned, and no house ads in the gap either.') }}</li>
            <li>{{ __('Rank follows market capitalisation as our data providers report it, one formula for every asset. We do not reorder the table by hand, and placement in it is not for sale.') }}</li>
            <li>{{ __('Risk flags follow liquidity and trading history. Nobody can pay to have one softened or taken off.') }}</li>
            <li>{{ __('One third-party request on the whole site: a cookieless visitor counter from Simple Analytics in Amsterdam. No cookie, no stored IP address.') }}</li>
            <li>{{ __('Fonts, icons, styles and scripts all come from this domain, so your browser never announces your visit to anyone else.') }}</li>
            <li>{{ __('Pages read from our own database. Your visit does not reach a market data provider.') }}</li>
        </ul>
        <p>{{ __('You do not have to take our word for any of it. The cookie policy lists every cookie and storage key we set by name, and the privacy policy covers what the counter measures, what happens to your IP address, and how to stay out of the count. Both are linked at the bottom of this page.') }}</p>

        <h2>{{ __('Who pays for it') }}</h2>
        <p>{{ __(':person does. Servers, domain and the paid data plans come out of his own pocket. No investor, no sponsor and no ad network setting targets, which is the only reason the pledge above holds.', ['person' => $person]) }}</p>
        <p>{{ __('Two things help without changing that. The Bitcoin address in the footer takes donations, and the Picks section on the home page names companies he uses or has a partnership with. Those are his interests, not paid placements and not commission slots. Every card states the relationship right next to the link, and the Disclosure of interests page lists them one by one.') }}</p>

        <h2>{{ __('What we will not do') }}</h2>
        <ul>
            <li>{{ __('Sell placement in the rankings, on a coin page, or on DexScan.') }}</li>
            <li>{{ __('Take money to add, soften or remove a risk flag.') }}</li>
            <li>{{ __('Add a tag manager, a session recorder, a heat map, or a second analytics tool.') }}</li>
            <li>{{ __('Sell, rent or share what you looked at.') }}</li>
            <li>{{ __('Put market data behind an account or a subscription.') }}</li>
            <li>{{ __('Run an ad-free tier, which is the same business with a nicer name.') }}</li>
        </ul>
        <p>{{ __('If any of that ever changes, it changes here and on the policy pages in the same release, not quietly.') }}</p>

        <h2>{{ __('Tell us when we slip') }}</h2>
        <p>{{ __('If something here reads like an ad, loads from a company we have not named, or slows a page down for no good reason, mail :email and point at it.', [
            'email' => $company['contact_email'],
        ]) }}</p>
    </article>

    <div class="afmc-callout afmc-callout--brand" style="margin-top:var(--space-6)">
        <x-afmc.icon name="info" filled class="afmc-callout__icon" />
        <div class="afmc-callout__content">
            <p class="afmc-callout__title">{{ __('The pages behind the claims') }}</p>
            <p class="afmc-callout__body">
                <a href="{{ route('legal.show', 'privacy-policy') }}" wire:navigate>{{ __('Privacy policy') }}</a>
                ·
                <a href="{{ route('legal.show', 'cookie-policy') }}" wire:navigate>{{ __('Cookie policy') }}</a>
                ·
                <a href="{{ route('legal.show', 'disclosure-of-interests') }}" wire:navigate>{{ __('Disclosure of interests') }}</a>
            </p>
        </div>
    </div>
</main>
