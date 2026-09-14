@props([
    'global' => null,
    'status' => null,
])

@php
    use App\Services\MarketData\MarketNumberFormatter;
    use App\Services\MarketData\MarketOverviewService;

    $overview = app(MarketOverviewService::class);

    // The middle track of the tallest panel sets the height of all three, so a panel holding
    // only a figure would open a continuous empty band below it. The market-cap panel carries
    // the 24 hour line and the dominance split, both of which the strip already implies.
    // Line and percentage come from one call, so the chart cannot contradict its own number.
    ['series' => $capSeries, 'change' => $capChange] = $overview->marketCapTrend();
    $dominance = $overview->dominanceSplit();

    $season = $status?->altcoin_season_index !== null ? (float) $status->altcoin_season_index : null;
    $sampleSize = $status?->altcoin_season_sample_size;

    $afmcPeriods = [
        ['label' => __('24h'), 'value' => $status?->afmc10_change_24h],
        ['label' => __('7d'), 'value' => $status?->afmc10_change_7d],
        ['label' => __('1m'), 'value' => $status?->afmc10_change_30d],
        ['label' => __('6m'), 'value' => $status?->afmc10_change_200d],
        ['label' => __('1y'), 'value' => $status?->afmc10_change_1y],
    ];
@endphp

{{-- One grid, not four boxes: sentiment, market cap and our own index sit in three equal
     panels with the same label / figure / caption silhouette. The altcoin-season scale gets
     its own full-width row, because its zone labels are unreadable in a third of the width. --}}
<section {{ $attributes->class('afmc-card afmc-market-status') }} aria-label="{{ __('Market status') }}">
    <div data-afmc-status>
        <div class="afmc-status-panel">
            <p class="afmc-status-panel__label">{{ __('Fear & greed') }}</p>
            <div class="afmc-status-panel__body afmc-status-panel__body--center">
                <x-afmc.gauge-dial
                    :value="$status?->fear_greed_value"
                    :label="$status?->fear_greed_classification"
                />
            </div>
            <p class="afmc-status-panel__caption">
                {{ __('Index by') }}
                <a href="https://alternative.me/crypto/fear-and-greed-index/" target="_blank" rel="noopener noreferrer">Alternative.me</a>
            </p>
        </div>

        <div class="afmc-status-panel">
            <p class="afmc-status-panel__label">{{ __('Total market cap') }}</p>
            <div class="afmc-status-panel__body">
                <p class="afmc-status-panel__figure">
                    {{ MarketNumberFormatter::money($global?->total_market_cap !== null ? (float) $global->total_market_cap : null) }}
                    <x-afmc.price-change :value="$capChange" />
                </p>
                @if ($capSeries !== [])
                    {{-- Colour follows the percentage above the line, which is measured from
                         the endpoints of this same series, so the two always agree. --}}
                    <x-afmc.sparkline
                        :data="$capSeries"
                        :up="$capChange === null ? null : $capChange >= 0"
                        :width="260"
                        :height="48"
                        class="afmc-status-panel__spark"
                    />
                @endif
                @if ($dominance !== [])
                    <div class="afmc-dominance">
                        <div class="afmc-dominance__track" aria-hidden="true">
                            @foreach ($dominance as $share)
                                <span class="afmc-dominance__band" style="width:{{ $share['percent'] }}%"></span>
                            @endforeach
                        </div>
                        <p class="afmc-dominance__legend">
                            @foreach ($dominance as $share)
                                <span class="afmc-dominance__item">
                                    <span class="afmc-dominance__dot" aria-hidden="true"></span>
                                    {{ $share['label'] }}
                                    <span class="afmc-dominance__value">{{ number_format($share['percent'], 1) }}%</span>
                                </span>
                            @endforeach
                        </p>
                    </div>
                @endif
            </div>
            <div class="afmc-status-panel__row">
                <span class="afmc-status-panel__row-label">{{ __('Volume 24h') }}</span>
                <span class="afmc-status-panel__row-value">
                    {{ MarketNumberFormatter::money($global?->total_volume_24h !== null ? (float) $global->total_volume_24h : null) }}
                </span>
            </div>
        </div>

        <div class="afmc-status-panel">
            <p class="afmc-status-panel__label">{{ __('AFMC10') }}</p>
            <div class="afmc-status-panel__body">
                <p class="afmc-status-panel__figure afmc-status-panel__figure--mono">
                    @if ($status?->afmc10_value !== null)
                        {{ number_format((float) $status->afmc10_value, 2) }}
                    @else
                        {{ __('—') }}
                    @endif
                </p>
                <dl class="afmc-status-panel__periods">
                    @foreach ($afmcPeriods as $period)
                        <div>
                            <dt>{{ $period['label'] }}</dt>
                            <dd><x-afmc.price-change :value="$period['value'] !== null ? (float) $period['value'] : null" size="sm" /></dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            {{-- A number a reader cannot look up anywhere else has to explain itself here, and
                 the sentence has to match MarketStatusCalculator: a fixed basket, market-cap
                 weighted, based on the first sum we recorded. --}}
            <p class="afmc-status-panel__caption">
                {{ __('A fixed basket of ten major coins, weighted by market cap and set to 100 the first time we measured it.') }}
            </p>
        </div>
    </div>

    <div class="afmc-market-status__season">
        <div class="afmc-market-status__season-head">
            <p class="afmc-status-panel__label">{{ __('Altcoin season') }}</p>
            <p class="afmc-status-panel__caption">
                @if ($sampleSize !== null && (int) $sampleSize > 0)
                    {{ __('Share of the :count largest alts that beat Bitcoin over 90 days', ['count' => number_format((int) $sampleSize)]) }}
                @else
                    {{ __('Share of the largest alts that beat Bitcoin over 90 days') }}
                @endif
            </p>
        </div>
        <x-afmc.threshold-bar
            :value="$season"
            :zones="[
                ['to' => 25, 'label' => __('Bitcoin season')],
                ['to' => 75, 'label' => __('Neutral')],
                ['to' => 100, 'label' => __('Altcoin season')],
            ]"
        />
    </div>
</section>
