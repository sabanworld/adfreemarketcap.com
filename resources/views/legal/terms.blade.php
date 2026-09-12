@php
    $company = config('company');
    $address = $company['address'];
    $consumer = $company['authorities']['consumer'];
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __('These terms govern your use of :product, which is operated by :legal, KvK :kvk, at :street, :postal :city, :country. Using the site means you accept them.', [
    'product' => $company['product_name'],
    'legal' => $company['legal_name'],
    'kvk' => $company['kvk'],
    'street' => $address['street'],
    'postal' => $address['postal_code'],
    'city' => $address['city'],
    'country' => $address['country'],
]) }}</p>

<h2>{{ __('What the service is') }}</h2>
<p>{{ __('We publish cryptocurrency market information: rankings by market capitalisation, coin pages, and on-chain pair data. Rankings follow one published formula and cannot be bought. Picks name any interest our creator has in the company on the card itself.') }}</p>
<p>{{ __('The site is free. There is no subscription, no payment, and no purchase, so the rules on distance contracts for paid services and the associated 14 day withdrawal right do not come into play. If we ever charge for something, you will be told the price and your rights before you agree to it.') }}</p>

<h2>{{ __('We are not a regulated financial firm') }}</h2>
<p>{{ __('We publish information. We do not hold, transfer, exchange, or manage crypto-assets for anyone, we take no orders, and we give no advice. That means we are not a crypto-asset service provider under Regulation (EU) 2023/1114 (MiCA) and we are not licensed or supervised by the Dutch Authority for the Financial Markets (AFM) or De Nederlandsche Bank (DNB).') }}</p>
<p>{{ __('Nothing on this site is investment, legal, or tax advice, a recommendation, or an offer of any crypto-asset. Nothing here is a marketing communication for an offer to the public of a crypto-asset. Read the Risk disclosure before you act on anything you see here.') }}
    <a href="{{ route('legal.show', 'risk-disclosure') }}" wire:navigate>{{ __('Read the Risk disclosure') }}</a>
</p>

<h2>{{ __('Accounts') }}</h2>
<p>{{ __('An account is optional and only unlocks the watchlist. You must be 16 or older, give a working email address, keep your password to yourself, and stay responsible for what happens under your login. Tell us if you think someone else has access.') }}</p>
<p>{{ __('You can delete your account at any time by writing to :email, and we then erase it as described in the Privacy policy. We may suspend or close an account that breaks these terms, attacks the service, or is used for unlawful purposes, and we tell you why unless the law prevents us.', [
    'email' => $company['contact_email'],
]) }}</p>

<h2>{{ __('How you may use the site') }}</h2>
<ul>
    <li>{{ __('Read, share, and quote our pages with a link back to the source.') }}</li>
    <li>{{ __('Do not scrape at a rate that burdens the service, and do not work around rate limits or the anti-spam check.') }}</li>
    <li>{{ __('Do not copy the database wholesale or resell our data as your own product.') }}</li>
    <li>{{ __('Do not attempt to break, probe, or overload the service, and do not upload anything unlawful.') }}</li>
</ul>
<p>{{ __('The site, its design, its texts, and its code stay ours or our licensors\'. Market data comes from third-party providers and remains subject to their rights. Names and logos of other companies belong to those companies.') }}</p>

<h2>{{ __('Accuracy and availability') }}</h2>
<p>{{ __('Market data is collected from third-party providers on a schedule, so figures can be delayed, incomplete, or wrong, and a provider outage can leave a page stale. We give no guarantee that the site is available without interruption or that any figure is correct at the moment you read it.') }}</p>

<h2>{{ __('Liability') }}</h2>
<p>{{ __('Because the service is free and informational, we are not liable for decisions you make on the strength of it, for lost profit, or for lost opportunity. We are liable for damage caused by our intent or gross negligence, for death or personal injury, and for anything else that Dutch law does not allow us to exclude.') }}</p>
<p>{{ __('These terms never take away the rights you have as a consumer under mandatory EU or Dutch law.') }}</p>

<h2>{{ __('Changes') }}</h2>
<p>{{ __('We may change these terms when the service, the law, or our providers change. The date at the top shows the current version. For a change that materially affects account holders we send an email first, and continuing to use the account after that means accepting the new version.') }}</p>

<h2>{{ __('Disputes and applicable law') }}</h2>
<p>{{ __('Dutch law applies. If you are a consumer, the mandatory consumer protections of the country where you live continue to apply under article 6 of the Rome I Regulation, and you may bring a claim in the courts of that country. Other disputes go to the competent court in the Netherlands.') }}</p>
<p>{{ __('Please write to :email first, because most complaints are settled that way. The Complaints page explains the steps and the timescales. Consumers in the Netherlands can also get free guidance from :consumer.', [
    'email' => $company['contact_email'],
    'consumer' => $consumer['name'],
]) }}
    <a href="{{ $consumer['url'] }}" rel="noopener noreferrer" target="_blank">{{ $consumer['url'] }}</a>
</p>
