@props([
    'id' => 'pledge',
    'detail' => null,
])

@php
    $person = config('company.person');
@endphp

<section id="{{ $id }}" {{ $attributes->class('afmc-pledge') }}>
    <div class="afmc-pledge__top">
        <div>
            <h2 class="afmc-pledge__statement">{{ __('No ads. No paid rankings. No sponsored listings.') }}</h2>
            <p class="afmc-pledge__detail">
                {{ $detail ?? __(':person pays for this site out of his own pocket.', ['person' => $person]) }}
            </p>
        </div>
    </div>
    <div class="afmc-pledge__items">
        <div>
            <div class="afmc-pledge__item-label"><x-afmc.icon name="block" />{{ __('Zero ad slots') }}</div>
            <p class="afmc-pledge__item-detail">{{ __('None sold, none planned, no house ads.') }}</p>
        </div>
        <div>
            <div class="afmc-pledge__item-label"><x-afmc.icon name="balance" />{{ __('Rank is market cap') }}</div>
            <p class="afmc-pledge__item-detail">{{ __('One formula, published, applied to every asset.') }}</p>
        </div>
        <div>
            <div class="afmc-pledge__item-label"><x-afmc.icon name="visibility" />{{ __('Risk flags are ours') }}</div>
            <p class="afmc-pledge__item-detail">{{ __('Set by liquidity and history, never negotiable.') }}</p>
        </div>
        <div>
            <div class="afmc-pledge__item-label"><x-afmc.icon name="savings" />{{ __('Funded by :person', ['person' => $person]) }}</div>
            <p class="afmc-pledge__item-detail">{{ __('Out of pocket. No ads, no sponsors, no investors to keep happy.') }}</p>
        </div>
    </div>
</section>
