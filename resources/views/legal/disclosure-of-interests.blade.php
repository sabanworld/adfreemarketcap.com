@php
    $company = config('company');
    $picks = config('picks');
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __(':product sells no display ads, no sponsored listings, and no paid rank boosts. :legal pays for the site, which is why nothing on it is for sale to the companies we write about.', [
    'product' => $company['product_name'],
    'legal' => $company['legal_name'],
]) }}</p>

<h2>{{ __('How rankings are decided') }}</h2>
<p>{{ __('Rankings come from market capitalisation as reported by our data providers, using one formula for every asset. Risk flags follow liquidity and trading history.') }}</p>

<h2>{{ __('How picks are labelled') }}</h2>
<p>{{ __('The Picks section names companies :legal uses, has a strategic partnership with, or earns a commission from. Every card carries a badge that states which applies, so you can see the relationship at the moment you read the recommendation instead of hunting for it in a policy page.', [
    'legal' => $company['legal_name'],
]) }}</p>
<p>{{ __('These are interests of :legal and of its director. Where a card earns :legal a commission, the badge names that interest in the same place as the other relationships. If that labelling ever changes, this page is updated in the same release.', [
    'legal' => $company['legal_name'],
]) }}</p>
<p>{{ __('Each card links to the company it names, and the badge you see on the card is the relationship listed here. The ChangeNOW swap widget on the home page and on coin pages is the same affiliate interest as the ChangeNOW card.') }}</p>

<h2>{{ __('Who we name, and what our interest is') }}</h2>
<ul>
    @foreach ($picks as $pick)
        <li>
            @if ($pick['relationship'] === 'partner')
                {{ __(':name (:host): :legal has a strategic partnership with this company.', [
                    'name' => $pick['name'],
                    'host' => parse_url($pick['url'], PHP_URL_HOST),
                    'legal' => $company['legal_name'],
                ]) }}
            @elseif ($pick['relationship'] === 'affiliate')
                {{ __(':name (:host): :legal may earn a commission when you complete a swap through the widget on this site. :person uses the product himself.', [
                    'name' => $pick['name'],
                    'host' => parse_url($pick['url'], PHP_URL_HOST),
                    'legal' => $company['legal_name'],
                    'person' => $company['person'],
                ]) }}
            @else
                {{ __(':name (:host): :legal uses this product and has no commercial partnership with the company.', [
                    'name' => $pick['name'],
                    'host' => parse_url($pick['url'], PHP_URL_HOST),
                    'legal' => $company['legal_name'],
                ]) }}
            @endif
        </li>
    @endforeach
</ul>

<h2>{{ __('Why we state it on the card') }}</h2>
<p>{{ __('EU consumer rules treat a hidden commercial interest as a misleading practice, and the Digital Services Act requires advertising to be recognisable as advertising. We run no advertising on the site itself, and we still label our interests, because a reader deserves to know who benefits.') }}</p>

<h2>{{ __('Questions') }}</h2>
<p>{{ __('Ask us about any relationship on the site at :email. The company behind the product is described at :website.', [
    'email' => $company['contact_email'],
    'website' => $company['website'],
]) }}</p>
