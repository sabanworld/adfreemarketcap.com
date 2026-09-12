@php
    $company = config('company');
    $sessionCookie = (string) config('session.cookie');
    $sessionMinutes = (int) config('session.lifetime');

    $items = [
        [
            'name' => $sessionCookie,
            'kind' => __('Cookie'),
            'purpose' => __('Holds your session, so a signed-in page load knows who you are and figures appear in the currency you picked'),
            'expiry' => __(':minutes minutes after your last request', ['minutes' => $sessionMinutes]),
        ],
        [
            'name' => 'XSRF-TOKEN',
            'kind' => __('Cookie'),
            'purpose' => __('Carries the token that proves a form was submitted from this site, which blocks cross-site request forgery'),
            'expiry' => __(':minutes minutes after your last request', ['minutes' => $sessionMinutes]),
        ],
        [
            'name' => 'remember_web_*',
            'kind' => __('Cookie'),
            'purpose' => __('Keeps you signed in between visits, and is only set when you tick "Remember me" while logging in'),
            'expiry' => __('Five years, or until you sign out'),
        ],
        [
            'name' => 'afmc-theme',
            'kind' => __('Local storage'),
            'purpose' => __('Remembers whether you chose the light or the dark theme'),
            'expiry' => __('Until you clear site data in your browser'),
        ],
        [
            'name' => 'afmc-cookies',
            'kind' => __('Local storage'),
            'purpose' => __('Records the choice you made in the cookie bar so we stop asking'),
            'expiry' => __('Until you clear site data in your browser'),
        ],
    ];
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __('This policy lists the cookies and browser storage that :product uses. It follows article 11.7a of the Dutch Telecommunications Act, which implements the EU ePrivacy Directive, together with the GDPR.', [
    'product' => $company['product_name'],
]) }}</p>

<h2>{{ __('Only what the service needs') }}</h2>
<p>{{ __('Everything below is strictly necessary to deliver the pages you ask for, to keep you signed in, or to remember a choice you made. Storage of that kind does not need consent under article 5(3) of the ePrivacy Directive, so the bar you saw is a notice rather than a request.') }}</p>

<h2>{{ __('What is stored') }}</h2>
<div class="afmc-table-wrap">
<table class="afmc-legal-table">
    <thead>
        <tr>
            <th scope="col">{{ __('Name') }}</th>
            <th scope="col">{{ __('Kind') }}</th>
            <th scope="col">{{ __('Purpose') }}</th>
            <th scope="col">{{ __('Expires') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td><code>{{ $item['name'] }}</code></td>
                <td>{{ $item['kind'] }}</td>
                <td>{{ $item['purpose'] }}</td>
                <td>{{ $item['expiry'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
<p>{{ __('All of these are first-party items set by this domain. None of them are readable by another company.') }}</p>

<h2>{{ __('Statistics without cookies') }}</h2>
<p>{{ __('We count page views with Simple Analytics, a Dutch service. It sets no cookie, writes nothing to local storage, and uses no fingerprinting, so it adds no item to the table above. Storage is what article 5(3) of the ePrivacy Directive asks consent for, and this counter uses none, which is why the bar stays a notice.') }}</p>
<p>{{ __('Their script is the only file on this site that your browser fetches from another company. Fonts, styles, and icons are served from our own domain. The Privacy policy sets out what the counter measures, what happens to your IP address, and how to stay out of the count.') }}</p>
<p>{{ __('We set no advertising cookies, no social media cookies, and no analytics cookies. If we ever add storage that is not strictly necessary, it stays switched off until you accept it, refusing stays as easy as accepting, and this page changes in the same release.') }}</p>

<h2>{{ __('Managing what is stored') }}</h2>
<p>{{ __('There are no optional cookies to switch off, so the footer has no preference panel. Select "Cookie notice" in the footer to read the notice again. Your browser can also block or delete cookies and local storage for this site. Blocking the session cookie means you cannot sign in, but the market pages keep working.') }}</p>

<h2>{{ __('Contact') }}</h2>
<p>{{ __('Controller: :legal, KvK :kvk. Questions about this policy go to :email.', [
    'legal' => $company['legal_name'],
    'kvk' => $company['kvk'],
    'email' => $company['privacy_email'],
]) }}
    <a href="{{ route('legal.show', 'privacy-policy') }}" wire:navigate>{{ __('See also the Privacy policy') }}</a>
</p>
