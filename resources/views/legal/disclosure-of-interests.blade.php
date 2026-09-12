@php
    $company = config('company');
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __(':product sells no display ads, no sponsored listings, and no paid rank boosts. The creator pays for it, which is why nothing on the site is for sale to the companies we write about.', [
    'product' => $company['product_name'],
]) }}</p>

<h2>{{ __('How rankings are decided') }}</h2>
<p>{{ __('Rankings come from market capitalisation as reported by our data providers, using one formula for every asset. Risk flags follow liquidity and trading history. Neither can be bought, removed, or softened on request.') }}</p>

<h2>{{ __('How picks are labelled') }}</h2>
<p>{{ __('The Picks section names companies our creator uses or holds a stake in. Every card carries a badge that states which of the two applies, so you can see the relationship at the moment you read the recommendation instead of hunting for it in a policy page.') }}</p>
<p>{{ __('These are interests of the creator and of :legal. They are not paid placements and not commission-based affiliate slots. If that ever changes, the card will say so in the same place, and this page will be updated in the same release.', [
    'legal' => $company['legal_name'],
]) }}</p>

<h2>{{ __('Why we state it on the card') }}</h2>
<p>{{ __('EU consumer rules treat a hidden commercial interest as a misleading practice, and the Digital Services Act requires advertising to be recognisable as advertising. We run no advertising at all, and we still label our interests, because a reader deserves to know who benefits.') }}</p>

<h2>{{ __('Questions') }}</h2>
<p>{{ __('Ask us about any relationship on the site at :email. The company behind the product is described at :website.', [
    'email' => $company['contact_email'],
    'website' => $company['website'],
]) }}</p>
