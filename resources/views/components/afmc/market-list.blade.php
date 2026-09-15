@props([
    'coins' => [],
    'watchedIds' => [],
    'label' => null,
    'watchAction' => 'toggleWatch',
])

@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
    $watchedIds = array_map('intval', $watchedIds instanceof \Illuminate\Support\Collection ? $watchedIds->all() : (array) $watchedIds);
@endphp

{{-- The phone form of a ranked coin list. A row opens in place rather than navigating,
     because a tap that leaves the page loses the reader's position in the ranking; the coin
     page is the primary button inside the panel. One row is open at a time. --}}
<ul class="afmc-list" @if ($label) aria-label="{{ $label }}" @endif x-data="{ open: null }">
    @foreach ($coins as $index => $coin)
        @php
            $volumeToCap = $coin->volumeToMarketCapPercent();
            // Absolute, because a shared link has to work in whatever app the sheet hands it to.
            $coinUrl = route('coins.show', $coin);
        @endphp
        <li class="afmc-list__item" wire:key="row-{{ $coin->id }}">
            <button
                type="button"
                class="afmc-list__row"
                :class="open === {{ $coin->id }} && 'is-open'"
                @click="open = open === {{ $coin->id }} ? null : {{ $coin->id }}"
                aria-expanded="false"
                :aria-expanded="open === {{ $coin->id }} ? 'true' : 'false'"
                aria-controls="afmc-row-{{ $coin->slug }}"
            >
                <span class="afmc-list__top">
                    <x-afmc.coin-identity
                        :name="$coin->name"
                        :symbol="$coin->symbol"
                        :rank="$coin->rank"
                        :image="$coin->image_url"
                        :eager="$index < 8"
                    />
                    <span class="afmc-list__price">{{ MarketNumberFormatter::moneyRow($coin->price !== null ? (float) $coin->price : null) }}</span>
                    <x-afmc.icon name="expand_more" size="20px" class="afmc-list__caret" />
                </span>
                <span class="afmc-list__meta">
                    <span class="afmc-list__mc">{{ __('MC') }} {{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</span>
                    <x-afmc.sparkline :data="$display->sparkline($coin->sparkline_7d)" width="52" height="22" class="afmc-list__spark" />
                    <span data-afmc-row-1h class="afmc-list__window">
                        1h <x-afmc.price-change :value="$display->change($coin->percent_change_1h, '1h')" size="sm" :show-icon="false" />
                    </span>
                    <span class="afmc-list__window">
                        24h <x-afmc.price-change :value="$display->change($coin->percent_change_24h)" size="sm" :show-icon="false" />
                    </span>
                    <span class="afmc-list__window">
                        7d <x-afmc.price-change :value="$display->change($coin->percent_change_7d, '7d')" size="sm" :show-icon="false" />
                    </span>
                </span>
            </button>

            <div
                id="afmc-row-{{ $coin->slug }}"
                class="afmc-list__panel"
                x-show="open === {{ $coin->id }}"
                x-cloak
            >
                <dl data-afmc-panelgrid class="afmc-list__figures">
                    <div>
                        <dt>{{ __('Volume 24h') }}</dt>
                        <dd>{{ MarketNumberFormatter::money($coin->volume_24h !== null ? (float) $coin->volume_24h : null) }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Vol / cap') }}</dt>
                        <dd>{{ $volumeToCap !== null ? number_format($volumeToCap, 2).'%' : '—' }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Circulating') }}</dt>
                        <dd>
                            {{ $coin->circulating_supply !== null
                                ? MarketNumberFormatter::compact((float) $coin->circulating_supply).' '.strtoupper((string) $coin->symbol)
                                : '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="afmc-list__actions" x-data="afmcShare">
                    <x-afmc.watch-star
                        :coin-id="$coin->id"
                        :watched="in_array((int) $coin->id, $watchedIds, true)"
                        :action="$watchAction"
                        show-label
                    />

                    {{-- Price alerts are not built yet. There is no readable disabled grey, so the
                         button keeps its contrast and the Soon marker carries the state, the same
                         way the header does it for a nav item that does not exist yet. It stays
                         focusable so the reason reaches a screen reader too. --}}
                    <button
                        type="button"
                        class="afmc-panel-action is-soon"
                        aria-disabled="true"
                        title="{{ __('Coming soon') }}"
                        aria-label="{{ __('Price alerts, coming soon') }}"
                    >
                        <x-afmc.icon name="notifications" size="18px" color="var(--text-faint)" />
                        <span aria-hidden="true">{{ __('Alert') }}</span>
                        <span class="afmc-soon" aria-hidden="true">{{ __('Soon') }}</span>
                    </button>

                    <button
                        type="button"
                        class="afmc-panel-action"
                        x-show="supported"
                        x-cloak
                        @click="share(@js($coinUrl), @js($coin->name.' · '.$coin->symbol))"
                        :title="copied ? @js(__('Link copied')) : @js(__('Share :name', ['name' => $coin->name]))"
                        :aria-label="copied ? @js(__('Link copied')) : @js(__('Share :name', ['name' => $coin->name]))"
                    >
                        <x-afmc.icon name="ios_share" size="18px" x-show="! copied" />
                        <x-afmc.icon name="check" size="18px" color="var(--text-up)" x-show="copied" x-cloak />
                        <span aria-hidden="true" x-text="copied ? @js(__('Copied')) : @js(__('Share'))">{{ __('Share') }}</span>
                    </button>

                    {{-- The tick is visual, so the confirmation is announced separately. --}}
                    <span role="status" aria-live="polite" class="afmc-visually-hidden" x-text="copied ? @js(__('Link copied to your clipboard')) : ''"></span>
                </div>

                <a href="{{ $coinUrl }}" wire:navigate class="afmc-btn afmc-btn--primary afmc-btn--full">
                    {{ __(':name details', ['name' => $coin->name]) }}
                    <x-afmc.icon name="arrow_forward" size="16px" />
                </a>
            </div>
        </li>
    @endforeach
</ul>
