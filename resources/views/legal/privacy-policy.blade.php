@inject('consent', 'App\Services\Consent\ConsentService')

@php
    $company = config('company');
    $address = $company['address'];
    $dpa = $company['authorities']['data_protection'];
    $accountDays = $company['retention']['account_deletion_days'];
    $logDays = $company['retention']['server_log_days'];
    $nostrDays = $company['retention']['nostr_note_days'] ?? 30;
    $advertising = $consent->advertisingEnabled();

    // Google is only a recipient while the tag is actually shipped, so a build
    // without it must not name a company that never sees anything.
    $processors = $company['processors'];

    if (! $advertising) {
        unset($processors['advertising']);
    }

    $processing = [
        [
            'data' => __('Name, email address, and a hashed password'),
            'purpose' => __('Creating and running your account'),
            'basis' => __('Performance of a contract, article 6(1)(b) GDPR'),
            'retention' => __('While the account exists, then erased within :days days', ['days' => $accountDays]),
        ],
        [
            'data' => __('Coins you save to your watchlist'),
            'purpose' => __('Showing the watchlist you asked us to keep'),
            'basis' => __('Performance of a contract, article 6(1)(b) GDPR'),
            'retention' => __('Until you remove the item or delete your account'),
        ],
        [
            'data' => __('IP address, user agent, requested URL, and application error details in server logs and error reports'),
            'purpose' => __('Keeping the site available, finding faults, and blocking abuse'),
            'basis' => __('Legitimate interests in a secure service, article 6(1)(f) GDPR'),
            'retention' => __(':days days', ['days' => $logDays]),
        ],
        [
            'data' => __('Counters behind our rate limits and the ALTCHA anti-spam check'),
            'purpose' => __('Stopping automated sign-ups and password guessing'),
            'basis' => __('Legitimate interests in preventing abuse, article 6(1)(f) GDPR'),
            'retention' => __('Minutes to hours, then discarded automatically'),
        ],
        [
            'data' => __('Anything you write to us by email'),
            'purpose' => __('Answering your question, request, or complaint'),
            'basis' => __('Legitimate interests in handling correspondence, article 6(1)(f) GDPR'),
            'retention' => __('Up to two years after the matter is closed'),
        ],
        [
            'data' => __('Page address, referring page, campaign parameters, country from your IP address, device and browser type, and clicks on links leading off the site, sent to our statistics provider'),
            'purpose' => __('Counting page views and seeing which pages get read'),
            'basis' => __('Legitimate interests in measuring use of the site, article 6(1)(f) GDPR'),
            'retention' => __('Kept as counts. The identifier behind them is a hash with a salt that is deleted every 24 hours'),
        ],
        [
            'data' => __('Public Nostr note text, author pubkey, and display name cached for community remarks'),
            'purpose' => __('Showing curated community notes on coin pages without your browser contacting a relay'),
            'basis' => __('Legitimate interests in publishing public posts that authors already shared, article 6(1)(f) GDPR'),
            'retention' => __(':days days from the note date, then deleted', ['days' => $nostrDays]),
        ],
    ];

    if ($advertising) {
        $processing[] = [
            'data' => __('An advertising identifier in a cookie on your device, your IP address, and the page you reached, sent to Google'),
            'purpose' => __('Counting how many people who followed one of our Google adverts arrived here and created an account'),
            'basis' => __('Your consent, article 6(1)(a) GDPR, given in the cookie bar and withdrawable at any time'),
            'retention' => __('The cookie lasts 90 days on your device. Google keeps the reporting under its own retention rules'),
        ];
    }
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __('This policy explains what :legal does with personal data when you visit or use :product. It follows the General Data Protection Regulation (GDPR) and Dutch implementing law.', [
    'legal' => $company['legal_name'],
    'product' => $company['product_name'],
]) }}</p>

<h2>{{ __('Who is responsible') }}</h2>
<p>{{ __('The controller is :legal, :form, registered with the Dutch Chamber of Commerce under KvK :kvk, at :street, :postal :city, :country.', [
    'legal' => $company['legal_name'],
    'form' => $company['legal_form'],
    'kvk' => $company['kvk'],
    'street' => $address['street'],
    'postal' => $address['postal_code'],
    'city' => $address['city'],
    'country' => $address['country'],
]) }}</p>
<p>{{ __('Privacy questions and requests go to :email. We are not required to appoint a data protection officer and have not appointed one, so that address reaches the people who run the service.', [
    'email' => $company['privacy_email'],
]) }}</p>

<h2>{{ __('What we process, why, and for how long') }}</h2>
<div class="afmc-table-wrap">
<table class="afmc-legal-table">
    <thead>
        <tr>
            <th scope="col">{{ __('Data') }}</th>
            <th scope="col">{{ __('Purpose') }}</th>
            <th scope="col">{{ __('Legal basis') }}</th>
            <th scope="col">{{ __('Kept for') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($processing as $row)
            <tr>
                <td>{{ $row['data'] }}</td>
                <td>{{ $row['purpose'] }}</td>
                <td>{{ $row['basis'] }}</td>
                <td>{{ $row['retention'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
<p>{{ __('You can browse the market pages without an account. An account only needs an email address and a password, and giving us those is voluntary. Without them we cannot offer a watchlist.') }}</p>

<h2>{{ __('Visitor statistics') }}</h2>
@if ($advertising)
    <p>{{ __('We count page views with Plausible, an Estonian service that works without cookies and keeps its data in Germany. The counting code is part of our own bundle, so your browser fetches no file from them.') }}</p>
@else
    <p>{{ __('We count page views with Plausible, an Estonian service that works without cookies and keeps its data in Germany. The counting code is part of our own bundle, so your browser fetches no file from them and the count itself is the only request that leaves this site.') }}</p>
@endif
<p>{{ __('Each page view sends the address of the page without its query string, apart from campaign parameters such as utm_source, the page you came from, and your device, operating system, and browser. Clicks on links leading off this site, file downloads, and the fact that a form was submitted are counted the same way, without anything you typed into it. How far down a page you scrolled and how long it stayed open are sent as well. No cookie is set, nothing is written to your device, and no profile is built across pages or sites.') }}</p>
    <p>{{ __('The request that carries the count also carries your IP address, the way any request your browser makes does. Plausible reads your country, region, and city from it and then drops it, so it is never written to their logs, their database, or a disk. To count you once a day without a cookie, they hash your IP address and browser together with a salt that is deleted every 24 hours, which leaves nothing that can be traced back to you or matched to the next day.') }}</p>
<p>{{ __('Our basis is the legitimate interest in knowing which pages get read, under article 6(1)(f) GDPR. Since nothing is stored on your device, this counter needs no consent under article 5(3) of the ePrivacy Directive. Three ways to stay out of the count: switch on Do Not Track or Global Privacy Control in your browser and the counter never starts, set plausible_ignore to true in local storage for this site, or block requests to plausible.io. Each one leaves the site fully usable.') }}</p>

@if ($advertising)
    <h2>{{ __('Advertising measurement') }}</h2>
    <p>{{ __('We buy adverts on Google to bring people to this site. There are no adverts on the site itself and no space on it is for sale, so nothing in the rankings, on a coin page, or on DexScan is affected by this.') }}</p>
    <p>{{ __('To see which adverts are worth the money, we use the Google Ads conversion tag. It only runs if you choose Accept in the cookie bar. Until then your browser requests nothing from Google, because the tag starts with Google consent mode set to denied and the script is never placed on the page.') }}</p>
    <p>{{ __('Once you accept, Google writes a cookie on this domain that holds an advertising identifier, and receives your IP address and the address of the page you are on, the way any request your browser makes does. Google reports to us in totals, such as how many people an advert brought and how many of them created an account. We receive no list of individuals and we do not combine this with your account.') }}</p>
    <p>{{ __('Our legal basis is your consent, under article 6(1)(a) GDPR and article 11.7a of the Dutch Telecommunications Act. You can withdraw it in the footer under "Cookie preferences", which costs nothing, takes one click, and deletes the Google cookies from your browser. Withdrawal does not affect measuring that already happened.') }}</p>
    <p>{{ __('Google LLC is in the United States. Google Ireland Limited acts as our counterparty in the EU, the Google Ads data processing terms apply, and transfers rest on the European Commission standard contractual clauses together with the EU-US Data Privacy Framework, under which Google LLC is certified. The Cookie policy lists each cookie by name and how long it lasts.') }}</p>
@endif

<h2>{{ __('What we do not do') }}</h2>
<ul>
    <li>{{ __('We do not sell, rent, or trade personal data.') }}</li>
    @if ($advertising)
        <li>{{ __('We show no adverts on this site, sell no space on it, and run no ad network. We advertise the site elsewhere, and the Advertising measurement section above covers what that means for you.') }}</li>
        <li>{{ __('We use no fingerprinting, no session recording, no heat maps, and no tag manager.') }}</li>
        <li>{{ __('Fonts, styles, and icons come from our own domain, so the only requests that leave this site are the visitor counter and, if you accept it, the Google tag.') }}</li>
    @else
        <li>{{ __('We run no advertising and no ad networks.') }}</li>
        <li>{{ __('We use no fingerprinting and no cross-site tracking, and nothing follows you to another website.') }}</li>
        <li>{{ __('Fonts, styles, and icons come from our own domain, so the visitor counter above is the only request that leaves this site.') }}</li>
    @endif
    <li>{{ __('We build no behavioural profiles and take no automated decisions that have legal effects for you, in the sense of article 22 GDPR.') }}</li>
    <li>{{ __('Market data providers such as CoinGecko, CoinPaprika, and Alternative.me are called by our own servers on a schedule. Your requests are never forwarded to them and they receive no personal data about you.') }}</li>
    <li>{{ __('Public Nostr notes shown on some coin pages are fetched by our servers from a Nostr indexer and stored briefly so your browser never contacts a relay. Choosing View opens the note on Primal (primal.net).') }}</li>
</ul>

<h2>{{ __('Who else can see the data') }}</h2>
<p>{{ __('We use a small number of service providers. The ones that handle personal data for us do so on our instructions, under a data processing agreement as article 28 GDPR requires, and may only use the data to deliver their service to us. Plausible is one of them: your IP address reaches it while a page view is counted, and its data processing agreement at plausible.io/dpa sets out what it may do with it.') }}</p>
@if ($advertising)
    <p>{{ __('Google is the exception to that pattern. For advert measurement it decides some of its own purposes rather than acting only on our instructions, so we name it as a recipient and rely on your consent. It appears in the table for the same reason the others do: you should be able to see who is involved.') }}</p>
@endif
<div class="afmc-table-wrap">
<table class="afmc-legal-table">
    <thead>
        <tr>
            <th scope="col">{{ __('Service') }}</th>
            <th scope="col">{{ __('Provider') }}</th>
            <th scope="col">{{ __('Where data is processed') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($processors as $processor)
            <tr>
                <td>{{ __($processor['category']) }}</td>
                <td>{{ filled($processor['name']) ? $processor['name'] : __('Not yet appointed') }}</td>
                <td>
                    {{ $processor['location'] }}@if (filled($processor['transfer'])){{ __('. Safeguard for transfers outside the EEA: :safeguard.', ['safeguard' => $processor['transfer']]) }}@endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
<p>{{ __('We also disclose data when the law requires it, for example to a court or a supervisory authority. Where a provider processes data outside the European Economic Area, we rely on the European Commission standard contractual clauses or an adequacy decision, and you can ask us for a copy of the safeguard we use.') }}</p>

<h2>{{ __('Cookies and local storage') }}</h2>
@if ($advertising)
    <p>{{ __('Everything the service needs is set without asking, because it is strictly necessary. The advertising cookies are the only optional ones, and they are written only after you accept. The Cookie policy lists every item by name, says how long it lasts, and marks which ones wait for your consent.') }}
@else
    <p>{{ __('We only set storage that the service needs, and optional storage would need your consent first. The Cookie policy lists every item by name.') }}
@endif
    <a href="{{ route('legal.show', 'cookie-policy') }}" wire:navigate>{{ __('Read the Cookie policy') }}</a>
</p>

<h2>{{ __('How we protect data') }}</h2>
<p>{{ __('Traffic runs over HTTPS. Passwords are stored as bcrypt hashes and are never readable by us. Access to the database and the admin panel is limited to the people who operate the service, sign-in forms are rate limited, and a proof-of-work check filters automated abuse.') }}</p>

<h2>{{ __('Your rights') }}</h2>
<ul>
    <li>{{ __('Access to the personal data we hold about you, and a copy of it.') }}</li>
    <li>{{ __('Rectification of data that is wrong or incomplete.') }}</li>
    <li>{{ __('Erasure of your account and its data.') }}</li>
    <li>{{ __('Restriction of processing while a dispute is being resolved.') }}</li>
    <li>{{ __('Portability of the data you gave us, in a machine-readable file.') }}</li>
    <li>{{ __('Objection to processing based on our legitimate interests.') }}</li>
    <li>{{ __('Withdrawal of any consent you gave, at any time, without affecting what happened before you withdrew it.') }}</li>
</ul>
<p>{{ __('Email :email to use a right. We answer within one month and tell you in advance if a complex request needs longer, up to two further months. Using a right is free unless a request is clearly unfounded or excessive.', [
    'email' => $company['privacy_email'],
]) }}</p>

<h2>{{ __('Complaints') }}</h2>
<p>{{ __('If you think we handle your data wrongly, tell us first so we can fix it. You can also complain to the Dutch supervisory authority, :authority, or to the authority in the EU or EEA country where you live or work.', [
    'authority' => $dpa['name'],
]) }}
    <a href="{{ $dpa['url'] }}" rel="noopener noreferrer" target="_blank">{{ $dpa['url'] }}</a>
</p>

<h2>{{ __('Age') }}</h2>
<p>{{ __('The service is not aimed at children. Do not create an account if you are under 16. If you believe a child created one, write to us and we will remove it.') }}</p>

<h2>{{ __('Changes') }}</h2>
<p>{{ __('When this policy changes we update the date at the top. For a change that affects your rights or how we use your data, we also tell account holders by email before it takes effect.') }}</p>
