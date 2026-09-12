@php
    $company = config('company');
    $consumer = $company['authorities']['consumer'];
    $dpa = $company['authorities']['data_protection'];
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __('If something about :product is wrong, tell us and we will look at it. This page explains where to write, what happens next, and where to go if our answer does not satisfy you.', [
    'product' => $company['product_name'],
]) }}</p>

<h2>{{ __('How to complain') }}</h2>
<p>{{ __('Email :email with what happened, when it happened, the page or account involved, and how we can reach you. Complaining costs nothing and you do not need a lawyer.', [
    'email' => $company['contact_email'],
]) }}</p>

<h2>{{ __('What we do with it') }}</h2>
<ul>
    <li>{{ __('We confirm that we received your complaint within five business days.') }}</li>
    <li>{{ __('We give a substantive answer within 14 days. If we need longer, we say so within those 14 days and tell you when to expect our answer.') }}</li>
    <li>{{ __('We tell you what we found, what we changed, and what we could not do.') }}</li>
</ul>

<h2>{{ __('If our answer does not satisfy you') }}</h2>
<ul>
    <li>{{ __('Consumers in the Netherlands can get free guidance from :consumer at :consumer_url.', [
        'consumer' => $consumer['name'],
        'consumer_url' => $consumer['url'],
    ]) }}</li>
    <li>{{ __('Consumers elsewhere in the EU can ask the consumer authority or a recognised out-of-court dispute body in their own country. We take part in dispute resolution where the law obliges us to.') }}</li>
    <li>{{ __('For a complaint about personal data, you can go to :authority at :authority_url, or to the supervisory authority of the EU or EEA country where you live or work.', [
        'authority' => $dpa['name'],
        'authority_url' => $dpa['url'],
    ]) }}</li>
    <li>{{ __('You keep the right to go to court. Nothing on this page limits your statutory rights under Dutch or EU law.') }}</li>
</ul>

<h2>{{ __('Operator') }}</h2>
<p>{{ __(':legal, KvK :kvk. Privacy requests go to :privacy.', [
    'legal' => $company['legal_name'],
    'kvk' => $company['kvk'],
    'privacy' => $company['privacy_email'],
]) }}</p>
