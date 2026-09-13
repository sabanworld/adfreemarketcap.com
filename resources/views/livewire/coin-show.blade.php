@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;
    use Illuminate\Support\Str;

    $display = app(MarketDisplayService::class);
    $unit = $display->unit();

    $labels = $chartLabels ?? [];
    $values = array_map(static fn (array $point): float => $point[1], $chartPoints ?? []);

    $change24 = $display->change($coin->percent_change_24h);
    $chartUp = ($change24 ?? 0) >= 0;
    $volCap = null;
    if ($coin->market_cap !== null && (float) $coin->market_cap > 0 && $coin->volume_24h !== null) {
        $volCap = ((float) $coin->volume_24h / (float) $coin->market_cap) * 100;
    }
@endphp

<main data-afmc-page class="afmc-page afmc-page--detail">
    <a href="{{ route('home') }}" wire:navigate class="afmc-back">
        <x-afmc.icon name="arrow_back" size="16px" />
        {{ __('All coins') }}
    </a>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-6);margin-bottom:var(--space-5);flex-wrap:wrap">
        <div style="display:grid;gap:var(--space-3)">
            <x-afmc.coin-identity
                :name="$coin->name"
                :symbol="$coin->symbol"
                :rank="$coin->rank"
                :image="$coin->image_url"
                size="lg"
                eager
            />
            <div style="display:flex;align-items:flex-end;gap:var(--space-3);flex-wrap:wrap">
                <span class="afmc-num-lg">{{ MarketNumberFormatter::money($coin->price !== null ? (float) $coin->price : null, 8) }}</span>
                <x-afmc.price-change :value="$change24" chip size="lg" />
                <span style="font:var(--type-body-sm);color:var(--text-faint);padding-bottom:4px">{{ __('24h · :currency', ['currency' => $unit->displayCode()]) }}</span>
            </div>
        </div>
    </div>

    <div data-afmc-coingrid class="afmc-grid afmc-grid--detail">
        <div style="display:grid;gap:var(--space-3)">
            <section class="afmc-card">
                <div class="afmc-card__body">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-3);gap:var(--space-3);flex-wrap:wrap">
                        <span class="afmc-card__eyebrow">{{ __('Price') }}</span>
                        <div class="afmc-chart-ranges" role="group" aria-label="{{ __('Chart range') }}">
                            @foreach ($rangeMeta as $key => $meta)
                                @php
                                    $isAvailable = in_array($key, $availableRanges, true);
                                    $isActive = $chartRange === $key;
                                @endphp
                                <button
                                    type="button"
                                    class="afmc-chart-ranges__item {{ $isActive ? 'is-active' : '' }}"
                                    wire:click="setChartRange('{{ $key }}')"
                                    @disabled(! $isAvailable)
                                    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                    @if (! $isAvailable)
                                        title="{{ __('No history for this range yet') }}"
                                        aria-label="{{ __(':range · no history for this asset yet', ['range' => $meta['label']]) }}"
                                    @endif
                                >{{ $meta['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                    @if (count($values))
                        <div wire:ignore class="afmc-chart-frame">
                            <canvas
                                id="coin-chart"
                                height="280"
                            ></canvas>
                        </div>
                        @if ($unit->code !== 'usd' && ! $display->usesBaselineHistory())
                            <p style="margin:var(--space-2) 0 0;font:var(--type-body-sm);font-size:var(--text-xs);color:var(--text-faint)">
                                {{ __('History is converted from USD at the current :currency rate.', ['currency' => $unit->displayCode()]) }}
                            </p>
                        @endif
                    @else
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ __('Chart data will appear after the next chart sync.') }}</p>
                    @endif
                </div>
            </section>

            <section class="afmc-card">
                <div class="afmc-card__header">
                    <div>
                        <span class="afmc-card__eyebrow">{{ __('Key stats') }}</span>
                        <h2 class="afmc-card__title">{{ __(':symbol at a glance', ['symbol' => strtoupper((string) $coin->symbol)]) }}</h2>
                    </div>
                </div>
                <div class="afmc-card__body">
                    <dl class="afmc-statlist">
                        <div>
                            <dt>{{ __('Market cap') }}</dt>
                            <dd>
                                <x-afmc.price-change :value="$change24" size="sm" />
                                <span class="afmc-statlist__value">{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('Volume 24h') }}</dt>
                            <dd>
                                <span class="afmc-statlist__value">{{ MarketNumberFormatter::money($coin->volume_24h !== null ? (float) $coin->volume_24h : null) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('Vol / cap') }}</dt>
                            <dd>
                                <span class="afmc-statlist__note">{{ __('24h volume ÷ market cap') }}</span>
                                <span class="afmc-statlist__value">{{ $volCap !== null ? number_format($volCap, 2).'%' : '—' }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('Circulating supply') }}</dt>
                            <dd>
                                <span class="afmc-statlist__note">{{ strtoupper((string) $coin->symbol) }}</span>
                                <span class="afmc-statlist__value">{{ $coin->circulating_supply !== null ? number_format((float) $coin->circulating_supply) : '—' }}</span>
                            </dd>
                        </div>
                        @if ($treasury)
                            <div>
                                <dt>{{ __('Treasury holdings') }}</dt>
                                <dd>
                                    <span class="afmc-statlist__note">
                                        {{ __('Public companies · :dominance% of market cap', [
                                            'dominance' => number_format((float) ($treasury->market_cap_dominance ?? 0), 2),
                                        ]) }}
                                    </span>
                                    <span class="afmc-statlist__value">{{ number_format((float) $treasury->total_holdings, 0) }} {{ strtoupper((string) $coin->symbol) }}</span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="afmc-card" wire:poll.30s="refreshMarkets">
                <div class="afmc-card__header">
                    <div>
                        <span class="afmc-card__eyebrow">{{ __('Where it trades') }}</span>
                        <h2 class="afmc-card__title">{{ __(':name Markets', ['name' => $coin->name]) }}</h2>
                    </div>
                    <span class="afmc-live afmc-live--neutral">
                        <x-afmc.icon name="sensors" size="14px" />
                        {{ $coin->tickers_synced_at?->diffForHumans() ?? __('Syncing…') }}
                    </span>
                </div>
                <div class="afmc-card__body" style="display:grid;gap:var(--space-3)">
                    @if ($tickers->isEmpty())
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">
                            {{ __('Exchange markets will appear after the next ticker sync.') }}
                        </p>
                    @else
                        <div class="afmc-table-wrap" data-afmc-tablescroll role="region" aria-label="{{ __('Exchange markets') }}" tabindex="0">
                            <table class="afmc-table afmc-table--compact">
                                <thead>
                                    <tr>
                                        <th class="hide-narrow"><span>#</span></th>
                                        <th class="is-sticky is-sticky--name" style="left:0"><span>{{ __('Exchange') }}</span></th>
                                        <th><span>{{ __('Pair') }}</span></th>
                                        <th class="is-right"><span>{{ __('Price') }}</span></th>
                                        <th class="is-right hide-narrow"><span>{{ __('Volume 24h') }}</span></th>
                                        <th class="is-right hide-narrow"><span>{{ __('Share') }}</span></th>
                                        <th><span>{{ __('Trust') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tickers as $ticker)
                                        <tr @class(['is-muted' => $ticker->is_stale || $ticker->is_anomaly])>
                                            <td class="hide-narrow">{{ $ticker->rank }}</td>
                                            <td class="is-sticky is-sticky--name" style="left:0">
                                                @if ($ticker->trade_url)
                                                    <a href="{{ $ticker->trade_url }}" rel="noopener noreferrer sponsored" target="_blank" style="color:var(--text-strong);text-decoration:underline;text-underline-offset:2px">
                                                        {{ $ticker->exchange_name }}
                                                    </a>
                                                @else
                                                    {{ $ticker->exchange_name }}
                                                @endif
                                            </td>
                                            <td>{{ $ticker->pair }}</td>
                                            <td class="is-right">{{ MarketNumberFormatter::money($ticker->price_usd !== null ? (float) $ticker->price_usd : null, 8) }}</td>
                                            <td class="is-right hide-narrow">{{ MarketNumberFormatter::money($ticker->volume_24h_usd !== null ? (float) $ticker->volume_24h_usd : null) }}</td>
                                            <td class="is-right hide-narrow">{{ $ticker->volume_share_percent !== null ? number_format((float) $ticker->volume_share_percent, 2).'%' : '—' }}</td>
                                            <td>
                                                <span class="afmc-tag">{{ __($ticker->trustLabel()) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">
                            {{ __('Spot markets from our market-data provider, ordered by 24h volume. Prices refresh in the background; this table reloads every 30 seconds.') }}
                        </p>
                    @endif
                </div>
            </section>
        </div>

        <div style="display:grid;gap:var(--space-3)">
            <section class="afmc-card">
                <div class="afmc-card__header">
                    <div>
                        <span class="afmc-card__eyebrow">{{ __('Returns') }}</span>
                        <h2 class="afmc-card__title">{{ __('Performance') }}</h2>
                    </div>
                    @if ($display->baselineCode())
                        <span class="afmc-tag">{{ __('vs :currency', ['currency' => $display->baselineCode()]) }}</span>
                    @endif
                </div>
                <div class="afmc-card__body">
                    <dl class="afmc-statcols">
                        @foreach ([
                            ['1h', $coin->percent_change_1h],
                            ['24h', $coin->percent_change_24h],
                            ['7d', $coin->percent_change_7d],
                        ] as [$label, $value])
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd><x-afmc.price-change :value="$display->change($value, $label)" /></dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            @if ($cycle)
                <section class="afmc-card">
                    <div class="afmc-card__header">
                        <div>
                            <span class="afmc-card__eyebrow">{{ __('Indicators') }}</span>
                            <h2 class="afmc-card__title">{{ __(':name Market Cycles', ['name' => $coin->name]) }}</h2>
                        </div>
                        <span class="afmc-tag">{{ __('Pi Cycle · USD') }}</span>
                    </div>
                    <div class="afmc-card__body">
                        <dl class="afmc-statpairs afmc-statpairs--bleed">
                            <div>
                                <dt>{{ __('111-day MA') }}</dt>
                                <dd>{{ MarketNumberFormatter::moneyUsd($cycle->ma111 !== null ? (float) $cycle->ma111 : null) }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('350-day MA × 2') }}</dt>
                                <dd>{{ MarketNumberFormatter::moneyUsd($cycle->ma350x2 !== null ? (float) $cycle->ma350x2 : null) }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('Gap to top band') }}</dt>
                                <dd>{{ $cycle->ma_gap_percent !== null ? number_format((float) $cycle->ma_gap_percent, 1).'%' : '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('Cycle status') }}</dt>
                                <dd>{{ __(str_replace('_', ' ', (string) $cycle->pi_cycle_status)) }}</dd>
                            </div>
                        </dl>

                        @php
                            $cycleProgress = min(100, max(0, (float) ($cycle->cycle_progress_percent ?? 0)));
                        @endphp
                        <div class="afmc-supply">
                            <div class="afmc-supply__head">
                                <span class="afmc-supply__label">{{ __('Halving cycle progress') }}</span>
                                <span class="afmc-supply__right">{{ number_format($cycleProgress, 1) }}%</span>
                            </div>
                            <div class="afmc-supply__track" aria-hidden="true">
                                <span class="afmc-supply__fill" style="width:{{ $cycleProgress }}%"></span>
                            </div>
                            <div class="afmc-supply__foot">
                                <span>{{ __('Epoch :n', ['n' => $cycle->halving_epoch ?? '—']) }} · {{ __('Last') }} {{ optional($cycle->last_halving_at)->toFormattedDateString() }}</span>
                                <span>{{ __(':days days to next', ['days' => $cycle->days_until_halving ?? '—']) }}</span>
                            </div>
                        </div>

                        <p style="margin:var(--space-4) 0 0;font:var(--type-body-sm);color:var(--text-faint)">
                            {{ __('Pi Cycle compares the 111-day moving average with twice the 350-day average. A cross historically marked late-cycle tops. Not investment advice.') }}
                        </p>
                    </div>
                </section>
            @endif

            <x-afmc.mining-block :coin="$coin" />

            @if ($treasury && $holders->isNotEmpty())
                <section class="afmc-card">
                    <div class="afmc-card__header">
                        <div>
                            <span class="afmc-card__eyebrow">{{ __('Institutions') }}</span>
                            <h2 class="afmc-card__title">{{ __(':name Treasury Holdings', ['name' => $coin->name]) }}</h2>
                        </div>
                    </div>
                    <div>
                        <dl class="afmc-statpairs" style="border-bottom:1px solid var(--border-divider)">
                            <div>
                                <dt>{{ __('Total held') }}</dt>
                                <dd>{{ number_format((float) $treasury->total_holdings, 0) }}</dd>
                            </div>
                            <div>
                                <dt>{{ __(':currency value', ['currency' => $unit->displayCode()]) }}</dt>
                                <dd>{{ MarketNumberFormatter::money($treasury->total_value_usd !== null ? (float) $treasury->total_value_usd : null) }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('Market dominance') }}</dt>
                                <dd>{{ $treasury->market_cap_dominance !== null ? number_format((float) $treasury->market_cap_dominance, 2).'%' : '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('Public companies') }}</dt>
                                <dd>{{ number_format((int) $treasury->companies_count) }}</dd>
                            </div>
                        </dl>

                        <div class="afmc-table-wrap" data-afmc-tablescroll role="region" aria-label="{{ __('Bitcoin treasury holders') }}" tabindex="0">
                            <table class="afmc-table afmc-table--compact">
                                <thead>
                                    <tr>
                                        <th class="hide-narrow"><span>#</span></th>
                                        <th class="is-sticky is-sticky--name" style="left:0"><span>{{ __('Entity') }}</span></th>
                                        <th class="is-right"><span>{{ __('Holdings') }}</span></th>
                                        <th class="is-right"><span>{{ __('% supply') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($holders as $holder)
                                        <tr>
                                            <td class="hide-narrow">{{ $holder->rank }}</td>
                                            <td class="is-sticky is-sticky--name" style="left:0">
                                                <div style="font:var(--type-body-sm);color:var(--text-strong)">{{ $holder->name }}</div>
                                                <div style="font:var(--type-label);color:var(--text-faint)">
                                                    {{ collect([$holder->symbol, $holder->country])->filter()->implode(' · ') }}
                                                </div>
                                            </td>
                                            <td class="is-right">{{ number_format((float) $holder->total_holdings, 0) }}</td>
                                            <td class="is-right">{{ $holder->percentage_of_total_supply !== null ? number_format((float) $holder->percentage_of_total_supply, 3).'%' : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p style="margin:0;padding:var(--space-3) var(--space-4);font:var(--type-body-sm);font-size:var(--text-xs);color:var(--text-faint)">
                            {{ __('Top public-company treasuries. Figures are provider-reported and may lag filings.') }}
                        </p>
                    </div>
                </section>
            @endif

            @if ($coin->circulating_supply !== null)
                <section class="afmc-card">
                    <div class="afmc-card__header">
                        <div>
                            <span class="afmc-card__eyebrow">{{ __('Tokenomics') }}</span>
                            <h2 class="afmc-card__title">{{ __('Supply') }}</h2>
                        </div>
                    </div>
                    {{-- We do not store a max supply, so there is no share to fill a bar with.
                         The figure stands on its own rather than against an invented cap. --}}
                    <div class="afmc-card__body">
                        <dl class="afmc-statlist">
                            <div>
                                <dt>{{ __('Circulating') }}</dt>
                                <dd>
                                    <span class="afmc-statlist__note">{{ strtoupper((string) $coin->symbol) }}</span>
                                    <span class="afmc-statlist__value">{{ number_format((float) $coin->circulating_supply) }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>
            @endif

            @if (filled($coin->description))
                <section class="afmc-card">
                    <div class="afmc-card__header">
                        <div>
                            <span class="afmc-card__eyebrow">{{ __('About') }}</span>
                            <h2 class="afmc-card__title">{{ $coin->name }}</h2>
                        </div>
                    </div>
                    <div class="afmc-card__body">
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-body);white-space:pre-line">{{ Str::limit((string) \App\Support\PlainText::fromHtml($coin->description), 2000) }}</p>
                    </div>
                </section>
            @endif
        </div>
    </div>
</main>

@assets
    @vite('resources/js/coin-chart.js')
@endassets

@if (count($values))
    @script
    <script>
        const mount = (payload) => {
            if (typeof window.afmcMountCoinChart !== 'function') {
                return false;
            }

            window.afmcMountCoinChart(payload);

            return true;
        };

        const initial = {
            labels: @js($labels),
            values: @js($values),
            up: @js($chartUp),
            symbol: @js($unit->symbol),
            symbolAfter: @js((bool) $unit->symbolAfter),
        };

        // Vite entry may still be loading on the first paint.
        if (! mount(initial)) {
            let tries = 0;
            const timer = setInterval(() => {
                tries += 1;
                if (mount(initial) || tries > 40) {
                    clearInterval(timer);
                }
            }, 50);
        }

        // Range switches call $this->js(...); keep a listener for any future dispatches.
        $wire.on('afmc-chart-updated', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            mount(data ?? {});
        });
    </script>
    @endscript
@endif
