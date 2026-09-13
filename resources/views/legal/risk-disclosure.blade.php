@php
    $company = config('company');
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<div class="afmc-callout afmc-callout--warn">
    <x-afmc.icon name="warning" filled class="afmc-callout__icon" />
    <div class="afmc-callout__content">
        <p class="afmc-callout__body">{{ __('Crypto-assets are highly risky and largely unregulated. Their value can move sharply and you can lose all the money you put in.') }}</p>
    </div>
</div>

<h2>{{ __('What you are not protected by') }}</h2>
<ul>
    <li>{{ __('Crypto-assets are not covered by the EU deposit guarantee scheme or by any investor compensation scheme.') }}</li>
    <li>{{ __('Most crypto-assets fall outside the EU rules that protect shares, bonds, and funds. Where Regulation (EU) 2023/1114 (MiCA) applies, it regulates issuers and service providers, and it does not make an asset safe or its price stable.') }}</li>
    <li>{{ __('If a platform, issuer, or protocol fails, there is often no authority that can get your money back.') }}</li>
</ul>

<h2>{{ __('Risks to expect') }}</h2>
<ul>
    <li>{{ __('Prices can move by large percentages within hours, in both directions.') }}</li>
    <li>{{ __('Past performance says nothing about future results.') }}</li>
    <li>{{ __('Markets can become illiquid, which means you cannot sell at the price you see.') }}</li>
    <li>{{ __('Losing a private key or trusting the wrong custodian can mean a permanent loss.') }}</li>
    <li>{{ __('Tax treatment differs per country and can change.') }}</li>
</ul>

<h2>{{ __('On-chain pairs carry extra risk') }}</h2>
<p>{{ __('Pairs on the DexScan page are listed automatically from public on-chain data, not vetted by us. A contract can be written to block selling, liquidity can be pulled in one transaction, reported volume can be manufactured, and a token can copy the name and symbol of a well-known project. Check the contract address rather than the ticker.') }}</p>

<h2>{{ __('What this site is') }}</h2>
<p>{{ __('Information on :product is published for general information only. It is not advice, not a recommendation, not an offer, and not a marketing communication for any crypto-asset. Figures are collected from third-party providers and can be delayed or wrong.', [
    'product' => $company['product_name'],
]) }}</p>
<p>{{ __('Only put in money you can afford to lose entirely. If you are unsure, take independent advice from someone licensed to give it.') }}</p>
