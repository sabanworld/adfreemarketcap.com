@inject('consent', 'App\Services\Consent\ConsentService')

@php
    $company = config('company');
    $sessionCookie = (string) config('session.cookie');
    $sessionMinutes = (int) config('session.lifetime');
    $advertising = $consent->advertisingEnabled();

    $items = [
        [
            'name' => $sessionCookie,
            'kind' => __('Cookie'),
            'purpose' => __('Holds your session, so a signed-in page load knows who you are and figures appear in the currency you picked'),
            'expiry' => __(':minutes minutes after your last request', ['minutes' => $sessionMinutes]),
            'consent' => __('Strictly necessary'),
        ],
        [
            'name' => 'XSRF-TOKEN',
            'kind' => __('Cookie'),
            'purpose' => __('Carries the token that proves a form was submitted from this site, which blocks cross-site request forgery'),
            'expiry' => __(':minutes minutes after your last request', ['minutes' => $sessionMinutes]),
            'consent' => __('Strictly necessary'),
        ],
        [
            'name' => 'remember_web_*',
            'kind' => __('Cookie'),
            'purpose' => __('Keeps you signed in between visits, and is only set when you tick "Remember me" while logging in'),
            'expiry' => __('Five years, or until you sign out'),
            'consent' => __('Strictly necessary'),
        ],
        [
            'name' => 'afmc-theme',
            'kind' => __('Local storage'),
            'purpose' => __('Remembers whether you chose the light or the dark theme'),
            'expiry' => __('Until you clear site data in your browser'),
            'consent' => __('Strictly necessary'),
        ],
        [
            'name' => $advertising ? $consent->storageKey() : 'afmc-cookies',
            'kind' => __('Local storage'),
            'purpose' => $advertising
                ? __('Records the answer you gave in the cookie bar, so we honour it and stop asking')
                : __('Records the choice you made in the cookie bar so we stop asking'),
            'expiry' => __('Until you clear site data in your browser'),
            'consent' => __('Strictly necessary'),
        ],
    ];

    if ($advertising) {
        $items[] = [
            'name' => '_gcl_au',
            'kind' => __('Cookie, set by Google on our domain'),
            'purpose' => __('Lets Google Ads connect a visit to the advert that brought it, so we can count how many people an advert actually reached'),
            'expiry' => __('90 days'),
            'consent' => __('Only after you accept'),
        ];

        $items[] = [
            'name' => '_gcl_aw, _gcl_gb, _gac_*',
            'kind' => __('Cookie, set by Google on our domain'),
            'purpose' => __('Holds the click identifier from the advert you followed, and is only written when you arrive from a Google advert'),
            'expiry' => __('90 days'),
            'consent' => __('Only after you accept'),
        ];
    }
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __('This policy lists the cookies and browser storage that :product uses. It follows article 11.7a of the Dutch Telecommunications Act, which implements the EU ePrivacy Directive, together with the GDPR.', [
    'product' => $company['product_name'],
]) }}</p>

@if ($advertising)
    <h2>{{ __('Two kinds of storage') }}</h2>
    <p>{{ __('Most of what this site stores is strictly necessary to deliver the pages you ask for, to keep you signed in, or to remember a choice you made. Storage of that kind does not need consent under article 5(3) of the ePrivacy Directive.') }}</p>
    <p>{{ __('One thing is different. We advertise this site on Google, and measuring which adverts brought people here means letting Google write a cookie to your device. That is not necessary to run the site, so it happens only if you choose Accept in the cookie bar. Until you do, no file is requested from Google and no advertising cookie exists. Choosing Reject is one click, in a button the same size and colour as Accept, and it leaves the site working exactly as it did.') }}</p>
@else
    <h2>{{ __('Only what the service needs') }}</h2>
    <p>{{ __('Everything below is strictly necessary to deliver the pages you ask for, to keep you signed in, or to remember a choice you made. Storage of that kind does not need consent under article 5(3) of the ePrivacy Directive, so the bar you saw is a notice rather than a request.') }}</p>
@endif

<h2>{{ __('What is stored') }}</h2>
<div class="afmc-table-wrap">
<table class="afmc-legal-table">
    <thead>
        <tr>
            <th scope="col">{{ __('Name') }}</th>
            <th scope="col">{{ __('Kind') }}</th>
            <th scope="col">{{ __('Purpose') }}</th>
            <th scope="col">{{ __('Expires') }}</th>
            @if ($advertising)
                <th scope="col">{{ __('Needs consent') }}</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td><code>{{ $item['name'] }}</code></td>
                <td>{{ $item['kind'] }}</td>
                <td>{{ $item['purpose'] }}</td>
                <td>{{ $item['expiry'] }}</td>
                @if ($advertising)
                    <td>{{ $item['consent'] }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
</div>
@if ($advertising)
    <p>{{ __('All of these sit on this domain. The Google cookies are written by Google code running on our pages, which means other websites cannot read them, but Google can use what they hold to report on its own adverts.') }}</p>
@else
    <p>{{ __('All of these are first-party items set by this domain. None of them are readable by another company.') }}</p>
@endif

<h2>{{ __('Statistics without cookies') }}</h2>
<p>{{ __('We count page views with Plausible, a European service. It sets no cookie, writes nothing to local storage, and builds no fingerprint, so it adds no item to the table above. Storage is what article 5(3) of the ePrivacy Directive asks consent for, and this counter uses none, which is why it runs for everyone.') }}</p>
<p>{{ __('One thing it does read from local storage is a key named plausible_ignore, which exists only if you put it there yourself to opt out. It is never written by us or by the counter.') }}</p>
@if ($advertising)
    <p>{{ __('The Privacy policy sets out what the counter measures, what happens to your IP address, and how to stay out of the count.') }}</p>
@else
    <p>{{ __('The counting code is bundled with our own scripts, so the only request that leaves this site is the count itself. Fonts, styles, and icons are served from our own domain. The Privacy policy sets out what the counter measures, what happens to your IP address, and how to stay out of the count.') }}</p>
@endif

@if ($advertising)
    <h2>{{ __('Advertising measurement') }}</h2>
    <p>{{ __('We buy adverts on Google to bring people to this site. There are no adverts on the site itself, no space is for sale on it, and nothing in the rankings or on a coin page changes because of this.') }}</p>
    <p>{{ __('If you accept, your browser loads the Google tag from googletagmanager.com and Google writes the cookies listed above. They let Google report that someone who followed one of our adverts reached the site and did something we count, such as creating an account. Google receives your IP address and the address of the page you are on, because that is what any request your browser makes carries.') }}</p>
    <p>{{ __('Two things stay true whatever you choose. The tag runs with Google consent mode set to denied by default, so the first page load requests nothing from Google. And if you reject after having accepted, we delete the Google cookies from your browser rather than only stopping the next measurement.') }}</p>
    <p>{{ __('Our legal basis is your consent, under article 6(1)(a) GDPR and article 11.7a of the Dutch Telecommunications Act. You can take it back at any time, which costs you nothing and does not affect the measuring that already happened. The Privacy policy names Google as a recipient and covers the transfer of data to the United States.') }}</p>
@endif

<h2>{{ __('Managing what is stored') }}</h2>
@if ($advertising)
    <p>{{ __('Select "Cookie preferences" in the footer to see the bar again and change your answer in either direction. Whatever you pick is recorded straight away and applies from that moment. Your browser can also block or delete cookies and local storage for this site. Blocking the session cookie means you cannot sign in, but the market pages keep working.') }}</p>
@else
    <p>{{ __('There are no optional cookies to switch off, so the footer has no preference panel. Select "Cookie notice" in the footer to read the notice again. Your browser can also block or delete cookies and local storage for this site. Blocking the session cookie means you cannot sign in, but the market pages keep working.') }}</p>
    <p>{{ __('We set no advertising cookies, no social media cookies, and no analytics cookies. If we ever add storage that is not strictly necessary, it stays switched off until you accept it, refusing stays as easy as accepting, and this page changes in the same release.') }}</p>
@endif

<h2>{{ __('Contact') }}</h2>
<p>{{ __('Controller: :legal, KvK :kvk. Questions about this policy go to :email.', [
    'legal' => $company['legal_name'],
    'kvk' => $company['kvk'],
    'email' => $company['privacy_email'],
]) }}
    <a href="{{ route('legal.show', 'privacy-policy') }}" wire:navigate>{{ __('See also the Privacy policy') }}</a>
</p>
