@props([
    'pairs' => [],
    'label' => null,
])

@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\DexQualityAssessor;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
    $quality = app(DexQualityAssessor::class);
@endphp

{{-- The phone form of the DexScan table. Nine columns behind a sideways scroll gave a 390px
     screen the pair and the price, with liquidity, age and the quality tier off the right edge:
     the three facts that decide whether a pool is worth a look. A row opens in place instead,
     and the pair page is the primary button inside the panel. --}}
<ul class="afmc-list" @if ($label) aria-label="{{ $label }}" @endif x-data="{ open: null }">
    @foreach ($pairs as $pair)
        @php
            $tier = $quality->describe($pair);
            $audit = strtolower((string) $pair->audit_status);
            $tone = match ($audit) {
                'verified' => 'up',
                'partial' => 'warn',
                default => 'down',
            };
            $auditLabel = match ($audit) {
                'verified' => __('Verified'),
                'partial' => __('Partial'),
                default => __('Unverified'),
            };
        @endphp
        <li class="afmc-list__item" wire:key="pair-row-{{ $pair->id }}">
            <button
                type="button"
                class="afmc-list__row"
                :class="open === {{ $pair->id }} && 'is-open'"
                @click="open = open === {{ $pair->id }} ? null : {{ $pair->id }}"
                aria-expanded="false"
                :aria-expanded="open === {{ $pair->id }} ? 'true' : 'false'"
                aria-controls="afmc-pair-{{ $pair->slug }}"
            >
                <span class="afmc-list__top">
                    <span class="afmc-pairrow__identity">
                        <span class="afmc-pairrow__pair">{{ $pair->pair }}</span>
                        {{-- The chain, and the pool's name in the panel. The table gives this line
                             both, and a phone cannot: once the price has its characters, the
                             identity is about 126px, and "Uniswap V3 (Robinhood) · Robinhood
                             Chain" needs 220. The chain is the half that says which of several
                             pools trading this pair a row is. --}}
                        <span class="afmc-pairrow__venue">{{ $pair->chain }}</span>
                    </span>
                    <span class="afmc-list__price">{{ MarketNumberFormatter::moneyRow($pair->price !== null ? (float) $pair->price : null) }}</span>
                    <x-afmc.icon name="expand_more" size="20px" class="afmc-list__caret" />
                </span>
                <span class="afmc-list__meta">
                    <x-afmc.risk-level
                        :tier="$tier['tier']"
                        :label="__($tier['label'])"
                        :why="__($tier['why'])"
                        compact
                    />
                    <span class="afmc-list__window">
                        {{ __('Liq') }} {{ MarketNumberFormatter::money($pair->liquidity_usd !== null ? (float) $pair->liquidity_usd : null) }}
                    </span>
                    <span class="afmc-list__window">
                        24h <x-afmc.price-change :value="$display->change($pair->percent_change_24h)" size="sm" :show-icon="false" />
                    </span>
                </span>
            </button>

            <div
                id="afmc-pair-{{ $pair->slug }}"
                class="afmc-list__panel"
                x-show="open === {{ $pair->id }}"
                x-cloak
            >
                <dl data-afmc-panelgrid class="afmc-list__figures afmc-list__figures--pairs">
                    {{-- A whole line, because a pool name is prose length and the row above only
                         had room for the chain. --}}
                    <div class="afmc-pairrow__pool">
                        <dt>{{ __('Pool') }}</dt>
                        <dd>{{ $pair->dex }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Volume 24h') }}</dt>
                        <dd>{{ MarketNumberFormatter::money($pair->volume_24h !== null ? (float) $pair->volume_24h : null) }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Txns 24h') }}</dt>
                        <dd>{{ $pair->txns_24h !== null ? number_format($pair->txns_24h) : '—' }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Age') }}</dt>
                        <dd>{{ $pair->ageLabel() }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Contract') }}</dt>
                        <dd><span class="afmc-badge afmc-badge--{{ $tone }}">{{ $auditLabel }}</span></dd>
                    </div>
                </dl>

                {{-- The tier's reason is a title attribute in the table, and a phone has no
                     hover, so the sentence is printed here. --}}
                <p class="afmc-pairrow__why">{{ __($tier['why']) }}</p>

                <a href="{{ route('dexscan.pair', $pair) }}" wire:navigate class="afmc-btn afmc-btn--primary afmc-btn--full">
                    {{ __(':pair details', ['pair' => $pair->pair]) }}
                    <x-afmc.icon name="arrow_forward" size="16px" />
                </a>
            </div>
        </li>
    @endforeach
</ul>
