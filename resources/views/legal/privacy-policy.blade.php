@php
    $company = config('company');
    $address = $company['address'];
    $dpa = $company['authorities']['data_protection'];
    $accountDays = $company['retention']['account_deletion_days'];
    $logDays = $company['retention']['server_log_days'];
    $nostrDays = $company['retention']['nostr_note_days'] ?? 30;

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
            'data' => __('Page address, referring page, campaign parameters, time zone, and device and browser type, sent to our statistics provider'),
            'purpose' => __('Counting page views and seeing which pages get read'),
            'basis' => __('Legitimate interests in measuring use of the site, article 6(1)(f) GDPR'),
            'retention' => __('Kept as aggregate counts that hold no identifier pointing back to you'),
        ],
        [
            'data' => __('Public Nostr note text, author pubkey, and display name cached for community remarks'),
            'purpose' => __('Showing curated community notes on coin pages without your browser contacting a relay'),
            'basis' => __('Legitimate interests in publishing public posts that authors already shared, article 6(1)(f) GDPR'),
            'retention' => __(':days days from the note date, then deleted', ['days' => $nostrDays]),
        ],
    ];
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
<p>{{ __('We count page views with Simple Analytics, a Dutch service that works without cookies. Their script is the only third-party file your browser loads here. If your browser runs no JavaScript, one image counts the visit instead.') }}</p>
<p>{{ __('What gets measured is the page address, the page you came from, campaign parameters in the link you followed, your time zone, and your device and browser type. No cookie is set, nothing is written to your device, and no profile is built across pages or sites.') }}</p>
<p>{{ __('Fetching that script means your IP address reaches Simple Analytics, the way it does for any request your browser makes. They state that every IP address is dropped without being logged or stored, and that they read your country from your time zone instead of your IP. Their servers and their own suppliers are in the EU.') }}</p>
<p>{{ __('Our basis is the legitimate interest in knowing which pages get read, under article 6(1)(f) GDPR. Since nothing is stored on your device, this counter needs no consent under article 5(3) of the ePrivacy Directive. Two ways to stay out of the count: Simple Analytics discards visits from browsers that send Do Not Track, and blocking the script in your browser or extension leaves the site fully usable.') }}</p>

<h2>{{ __('What we do not do') }}</h2>
<ul>
    <li>{{ __('We do not sell, rent, or trade personal data.') }}</li>
    <li>{{ __('We run no advertising and no ad networks.') }}</li>
    <li>{{ __('We use no fingerprinting and no cross-site tracking, and nothing follows you to another website.') }}</li>
    <li>{{ __('Fonts, styles, and icons come from our own domain, so the visitor counter above is the only request that leaves this site.') }}</li>
    <li>{{ __('We build no behavioural profiles and take no automated decisions that have legal effects for you, in the sense of article 22 GDPR.') }}</li>
    <li>{{ __('Market data providers such as CoinGecko, CoinPaprika, and Alternative.me are called by our own servers on a schedule. Your requests are never forwarded to them and they receive no personal data about you.') }}</li>
    <li>{{ __('Public Nostr notes shown on some coin pages are fetched by our servers from a Nostr indexer and stored briefly so your browser never contacts a relay. Choosing View opens the note on Primal (primal.net).') }}</li>
</ul>

<h2>{{ __('Who else can see the data') }}</h2>
<p>{{ __('We use a small number of service providers. The ones that handle personal data for us do so on our instructions, under a data processing agreement as article 28 GDPR requires, and may only use the data to deliver their service to us. Our statistics provider receives no personal data, so it is named below without such an agreement.') }}</p>
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
        @foreach ($company['processors'] as $processor)
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
<p>{{ __('We only set storage that the service needs, and optional storage would need your consent first. The Cookie policy lists every item by name.') }}
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
