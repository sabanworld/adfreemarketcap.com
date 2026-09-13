@props([
    // Pass the coin on a detail page: the block then renders only for the coins
    // named in config('mining.coins'). Left out (home page) it always renders.
    'coin' => null,
])

@php
    $mining = config('mining');
    $partner = $mining['partner'];
    $block = $mining['last_block'];
    $product = trim($partner['name'].' '.$partner['product']);
    $blockUrl = $block['hash'] ? rtrim((string) $block['explorer'], '/').'/'.$block['hash'] : null;

    $forThisCoin = ! $coin
        || in_array(mb_strtolower((string) $coin->slug), $mining['coins'], true)
        || in_array(mb_strtolower((string) $coin->symbol), $mining['coins'], true);
@endphp

@if ($mining['enabled'] && $forThisCoin)
    <section id="mining" aria-label="{{ __('For bitcoin miners') }}" {{ $attributes->merge(['class' => 'afmc-mining']) }}>
        <div class="afmc-mining__head">
            <span class="afmc-mining__eyebrow">{{ __('Mining') }}</span>
            <span class="afmc-mining__badge">{{ __('Creator is a partner') }}</span>
        </div>

        <div class="afmc-mining__body">
            <h2 class="afmc-mining__title">{{ __('For bitcoin miners') }}</h2>
            <p class="afmc-mining__lead">
                {{ __('Mine via :product to take part in the network\'s decentralisation from just :sats sats. The pool buys hashrate on your behalf and aims it at one block, so you do not need a machine, a rack, or a power contract.', [
                    'product' => $product,
                    'sats' => number_format($mining['min_bid_sats']),
                ]) }}
            </p>
        </div>

        <dl class="afmc-mining__stats">
            <div class="afmc-mining__stat">
                <dt>{{ __('From') }}</dt>
                <dd>{{ number_format($mining['min_bid_sats']) }} {{ __('sats') }}</dd>
            </div>
            <div class="afmc-mining__stat">
                <dt>{{ __('Last block found') }}</dt>
                <dd>{{ __($block['found_at']) }}</dd>
            </div>
            <div class="afmc-mining__stat">
                <dt>{{ __('Block height') }}</dt>
                <dd>
                    @if ($blockUrl)
                        <a class="afmc-mining__block-link" href="{{ $blockUrl }}" rel="noopener noreferrer" target="_blank">
                            {{ number_format($block['height']) }}<span class="afmc-visually-hidden">{{ __(', opens in a new tab') }}</span>
                        </a>
                    @else
                        {{ number_format($block['height']) }}
                    @endif
                </dd>
            </div>
        </dl>

        <div class="afmc-mining__foot">
            <a class="afmc-btn afmc-btn--primary afmc-mining__cta" href="{{ $partner['url'] }}" rel="noopener noreferrer" target="_blank">
                {{ __('Mine via :product', ['product' => $product]) }}
                <x-afmc.icon name="arrow_outward" size="16px" />
            </a>
            <p class="afmc-mining__note">
                {{ __('A pool pays out only when it finds a block, so treat the sats you spend as spent. Our creator has a strategic partnership with :partner. This is not a paid placement and we take no commission.', [
                    'partner' => $partner['name'],
                ]) }}
            </p>
        </div>
    </section>
@endif
