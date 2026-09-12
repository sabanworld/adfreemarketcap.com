@php
    $company = config('company');
    $address = $company['address'];
    $fullAddress = $address['street'].', '.$address['postal_code'].' '.$address['city'].', '.$address['country'];
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __('These are the identification details required by article 5 of the EU e-Commerce Directive, as implemented in article 3:15d of the Dutch Civil Code.') }}</p>

<h2>{{ __('Operator') }}</h2>
<ul>
    <li>{{ __('Service: :product', ['product' => $company['product_name']]) }}</li>
    <li>{{ __('Legal name: :legal', ['legal' => $company['legal_name']]) }}</li>
    <li>{{ __('Legal form: :form', ['form' => $company['legal_form']]) }}</li>
    <li>{{ __('Registered office: :address', ['address' => $fullAddress]) }}</li>
    <li>{{ __('Trade register: Dutch Chamber of Commerce (KvK), number :kvk', ['kvk' => $company['kvk']]) }}</li>
    @if (filled($company['vat']))
        <li>{{ __('VAT identification number: :vat', ['vat' => $company['vat']]) }}</li>
    @endif
    <li>{{ __('Email: :email', ['email' => $company['contact_email']]) }}</li>
</ul>
<p>{{ __('Email is the fastest way to reach a person, and it is also the contact point for authorities and for users who need to notify us about content on the service.') }}</p>

<h2>{{ __('Responsible for the content') }}</h2>
<p>{{ __(':legal is responsible for the content of this website. The company also builds and hosts the product, and runs it without advertising or paid placements.', [
    'legal' => $company['legal_name'],
]) }}
    <a href="{{ $company['website'] }}" rel="noopener noreferrer" target="_blank">{{ $company['website'] }}</a>
</p>

<h2>{{ __('Supervision') }}</h2>
<p>{{ __('We publish market information and provide no crypto-asset services, so no financial supervisory authority licenses or supervises this service. The Terms explain that position in more detail.') }}
    <a href="{{ route('legal.show', 'terms') }}" wire:navigate>{{ __('Read the Terms & conditions') }}</a>
</p>

<h2>{{ __('Privacy and complaints') }}</h2>
<p>{{ __('Privacy requests go to :privacy. The Complaints page explains how we handle a complaint and where you can take it if our answer does not satisfy you.', [
    'privacy' => $company['privacy_email'],
]) }}
    <a href="{{ route('legal.show', 'complaints') }}" wire:navigate>{{ __('Read the Complaints page') }}</a>
</p>
