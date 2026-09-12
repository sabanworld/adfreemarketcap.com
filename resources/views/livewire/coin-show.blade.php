@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;
    use Illuminate\Support\Str;

    $display = app(MarketDisplayService::class);
    $unit = $display->unit();

    $labels = [];
    $values = [];
    foreach ($display->chart($coin->chart_7d) as [$timestamp, $value]) {
        $labels[] = date('M j H:i', (int) ($timestamp / 1000));
        $values[] = $value;
    }

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
            />
            <div style="display:flex;align-items:flex-end;gap:var(--space-3);flex-wrap:wrap">
                <span class="afmc-num-lg">{{ MarketNumberFormatter::money($coin->price !== null ? (float) $coin->price : null, 8) }}</span>
                <x-afmc.price-change :value="$change24" chip size="lg" />
                <span style="font:var(--type-body-sm);color:var(--text-faint);padding-bottom:4px">{{ __('24h · :currency', ['currency' => $unit->displayCode()]) }}</span>
            </div>
        </div>
    </div>

    <div class="afmc-grid afmc-grid--detail">
        <div style="display:grid;gap:var(--space-4)">
            <section class="afmc-card">
                <div class="afmc-card__body">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-3);gap:var(--space-2);flex-wrap:wrap">
                        <span class="afmc-card__eyebrow">{{ __('Price') }}</span>
                        <span class="afmc-tag">7D</span>
                    </div>
                    @if (count($values))
                        <canvas
                            id="coin-chart"
                            height="280"
                            data-labels='@json($labels)'
                            data-values='@json($values)'
                            data-up="{{ $chartUp ? '1' : '0' }}"
                            data-symbol="{{ $unit->symbol }}"
                            data-symbol-after="{{ $unit->symbolAfter ? '1' : '0' }}"
                        ></canvas>
                        @if ($unit->code !== 'usd' && ! $display->usesBaselineHistory())
                            <p style="margin:var(--space-2) 0 0;font:var(--type-body-sm);font-size:var(--text-xs);color:var(--text-faint)">
                                {{ __('History is converted from USD at the current :currency rate.', ['currency' => $unit->displayCode()]) }}
                            </p>
                        @endif
                    @else
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ __('Chart data will appear after the next detail sync.') }}</p>
                    @endif
                </div>
            </section>

            <div class="afmc-grid afmc-grid--stats">
                <div class="afmc-stat">
                    <span class="afmc-stat__label">{{ __('Market cap') }}</span>
                    <span class="afmc-stat__value">{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</span>
                    <x-afmc.price-change :value="$change24" chip size="sm" />
                </div>
                <div class="afmc-stat">
                    <span class="afmc-stat__label">{{ __('Volume 24h') }}</span>
                    <span class="afmc-stat__value">{{ MarketNumberFormatter::money($coin->volume_24h !== null ? (float) $coin->volume_24h : null) }}</span>
                </div>
                <div class="afmc-stat">
                    <span class="afmc-stat__label">{{ __('Vol / cap') }}</span>
                    <span class="afmc-stat__value">{{ $volCap !== null ? number_format($volCap, 2).'%' : '—' }}</span>
                    <span style="font:var(--type-body-sm);font-size:var(--text-xs);color:var(--text-faint)">{{ __('24h volume divided by market cap.') }}</span>
                </div>
                <div class="afmc-stat">
                    <span class="afmc-stat__label">{{ __('Circulating') }}</span>
                    <span class="afmc-stat__value">{{ $coin->circulating_supply !== null ? number_format((float) $coin->circulating_supply) : '—' }}</span>
                </div>
                @if ($treasury)
                    <div class="afmc-stat">
                        <span class="afmc-stat__label">{{ __('Treasury holdings') }}</span>
                        <span class="afmc-stat__value">{{ number_format((float) $treasury->total_holdings, 0) }} {{ strtoupper((string) $coin->symbol) }}</span>
                        <span style="font:var(--type-body-sm);font-size:var(--text-xs);color:var(--text-faint)">
                            {{ __('Public companies · :dominance% of market cap', [
                                'dominance' => number_format((float) ($treasury->market_cap_dominance ?? 0), 2),
                            ]) }}
                        </span>
                    </div>
                @endif
            </div>

            <section class="afmc-card" wire:poll.30s="refreshMarkets">
                <div class="afmc-card__header">
                    <div>
                        <span class="afmc-card__eyebrow">{{ __('Where it trades') }}</span>
                        <h2 class="afmc-card__title">{{ __(':name Markets', ['name' => $coin->name]) }}</h2>
                    </div>
                    <span class="afmc-live">
                        <span class="afmc-icon">sensors</span>
                        {{ $coin->tickers_synced_at?->diffForHumans() ?? __('Syncing…') }}
                    </span>
                </div>
                <div class="afmc-card__body" style="display:grid;gap:var(--space-3)">
                    @if ($tickers->isEmpty())
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">
                            {{ __('Exchange markets will appear after the next ticker sync.') }}
                        </p>
                    @else
                        <div class="afmc-table-wrap">
                            <table class="afmc-table afmc-table--compact">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Exchange') }}</th>
                                        <th>{{ __('Pair') }}</th>
                                        <th class="afmc-num">{{ __('Price') }}</th>
                                        <th class="afmc-num">{{ __('Volume 24h') }}</th>
                                        <th class="afmc-num">{{ __('Share') }}</th>
                                        <th>{{ __('Trust') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tickers as $ticker)
                                        <tr @class(['is-muted' => $ticker->is_stale || $ticker->is_anomaly])>
                                            <td class="afmc-num">{{ $ticker->rank }}</td>
                                            <td>
                                                @if ($ticker->trade_url)
                                                    <a href="{{ $ticker->trade_url }}" rel="noopener noreferrer sponsored" target="_blank" style="color:var(--text-strong);text-decoration:underline;text-underline-offset:2px">
                                                        {{ $ticker->exchange_name }}
                                                    </a>
                                                @else
                                                    {{ $ticker->exchange_name }}
                                                @endif
                                            </td>
                                            <td class="afmc-num">{{ $ticker->pair }}</td>
                                            <td class="afmc-num">{{ MarketNumberFormatter::money($ticker->price_usd !== null ? (float) $ticker->price_usd : null, 8) }}</td>
                                            <td class="afmc-num">{{ MarketNumberFormatter::money($ticker->volume_24h_usd !== null ? (float) $ticker->volume_24h_usd : null) }}</td>
                                            <td class="afmc-num">{{ $ticker->volume_share_percent !== null ? number_format((float) $ticker->volume_share_percent, 2).'%' : '—' }}</td>
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

        <div style="display:grid;gap:var(--space-4)">
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
                <div class="afmc-card__body" style="display:grid;gap:var(--space-3)">
                    @foreach ([
                        ['1h', $coin->percent_change_1h],
                        ['24h', $coin->percent_change_24h],
                        ['7d', $coin->percent_change_7d],
                    ] as [$label, $value])
                        <div class="afmc-meta-row">
                            <span style="font:var(--type-label);color:var(--text-muted)">{{ $label }}</span>
                            <x-afmc.price-change :value="$display->change($value, $label)" />
                        </div>
                    @endforeach
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
                    <div class="afmc-card__body" style="display:grid;gap:var(--space-4)">
                        <div class="afmc-cycle-grid">
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __('111-day MA') }}</span>
                                <span class="afmc-stat__value">{{ MarketNumberFormatter::moneyUsd($cycle->ma111 !== null ? (float) $cycle->ma111 : null) }}</span>
                            </div>
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __('350-day MA × 2') }}</span>
                                <span class="afmc-stat__value">{{ MarketNumberFormatter::moneyUsd($cycle->ma350x2 !== null ? (float) $cycle->ma350x2 : null) }}</span>
                            </div>
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __('Gap to top band') }}</span>
                                <span class="afmc-stat__value">{{ $cycle->ma_gap_percent !== null ? number_format((float) $cycle->ma_gap_percent, 1).'%' : '—' }}</span>
                            </div>
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __('Cycle status') }}</span>
                                <span class="afmc-stat__value" style="font:var(--type-body-sm)">{{ __(str_replace('_', ' ', (string) $cycle->pi_cycle_status)) }}</span>
                            </div>
                        </div>

                        <div>
                            <div class="afmc-meta-row" style="margin-bottom:var(--space-2)">
                                <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Halving cycle progress') }}</span>
                                <span style="font:var(--type-num)">{{ number_format((float) ($cycle->cycle_progress_percent ?? 0), 1) }}%</span>
                            </div>
                            <div class="afmc-progress" aria-hidden="true">
                                <span style="width: {{ min(100, max(0, (float) ($cycle->cycle_progress_percent ?? 0))) }}%"></span>
                            </div>
                            <div class="afmc-meta-row" style="margin-top:var(--space-2);font:var(--type-body-sm);color:var(--text-faint)">
                                <span>{{ __('Epoch :n', ['n' => $cycle->halving_epoch ?? '—']) }} · {{ __('Last') }} {{ optional($cycle->last_halving_at)->toFormattedDateString() }}</span>
                                <span>{{ __(':days days to next', ['days' => $cycle->days_until_halving ?? '—']) }}</span>
                            </div>
                        </div>

                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">
                            {{ __('Pi Cycle compares the 111-day moving average with twice the 350-day average. A cross historically marked late-cycle tops. Not investment advice.') }}
                        </p>
                    </div>
                </section>
            @endif

            @if ($treasury && $holders->isNotEmpty())
                <section class="afmc-card">
                    <div class="afmc-card__header">
                        <div>
                            <span class="afmc-card__eyebrow">{{ __('Institutions') }}</span>
                            <h2 class="afmc-card__title">{{ __(':name Treasury Holdings', ['name' => $coin->name]) }}</h2>
                        </div>
                    </div>
                    <div class="afmc-card__body" style="display:grid;gap:var(--space-3)">
                        <div class="afmc-cycle-grid">
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __('Total held') }}</span>
                                <span class="afmc-stat__value">{{ number_format((float) $treasury->total_holdings, 0) }}</span>
                            </div>
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __(':currency value', ['currency' => $unit->displayCode()]) }}</span>
                                <span class="afmc-stat__value">{{ MarketNumberFormatter::money($treasury->total_value_usd !== null ? (float) $treasury->total_value_usd : null) }}</span>
                            </div>
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __('Market dominance') }}</span>
                                <span class="afmc-stat__value">{{ $treasury->market_cap_dominance !== null ? number_format((float) $treasury->market_cap_dominance, 2).'%' : '—' }}</span>
                            </div>
                            <div class="afmc-stat">
                                <span class="afmc-stat__label">{{ __('Public companies') }}</span>
                                <span class="afmc-stat__value">{{ number_format((int) $treasury->companies_count) }}</span>
                            </div>
                        </div>

                        <div class="afmc-table-wrap">
                            <table class="afmc-table afmc-table--compact">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Entity') }}</th>
                                        <th class="afmc-num">{{ __('Holdings') }}</th>
                                        <th class="afmc-num">{{ __('% supply') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($holders as $holder)
                                        <tr>
                                            <td class="afmc-num">{{ $holder->rank }}</td>
                                            <td>
                                                <div style="font:var(--type-body-sm);color:var(--text-strong)">{{ $holder->name }}</div>
                                                <div style="font:var(--type-label);color:var(--text-faint)">
                                                    {{ collect([$holder->symbol, $holder->country])->filter()->implode(' · ') }}
                                                </div>
                                            </td>
                                            <td class="afmc-num">{{ number_format((float) $holder->total_holdings, 0) }}</td>
                                            <td class="afmc-num">{{ $holder->percentage_of_total_supply !== null ? number_format((float) $holder->percentage_of_total_supply, 3).'%' : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">
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
                    <div class="afmc-card__body">
                        <div class="afmc-supply">
                            <div class="afmc-meta-row">
                                <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Circulating') }}</span>
                                <span class="afmc-num">{{ number_format((float) $coin->circulating_supply) }}</span>
                            </div>
                        </div>
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
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-body);white-space:pre-line">{{ Str::limit(strip_tags((string) $coin->description), 2000) }}</p>
                    </div>
                </section>
            @endif
        </div>
    </div>
</main>

@script
<script>
    const canvas = document.getElementById('coin-chart');
    if (canvas && window.Chart) {
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');
        const up = canvas.dataset.up === '1';
        const symbol = canvas.dataset.symbol || '$';
        const symbolAfter = canvas.dataset.symbolAfter === '1';
        const stroke = up ? '#0E9F6E' : '#D8433B';
        const fill = up ? 'rgba(14, 159, 110, 0.12)' : 'rgba(216, 67, 59, 0.12)';

        if (canvas._afmcChart) {
            canvas._afmcChart.destroy();
        }

        canvas._afmcChart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: stroke,
                    backgroundColor: fill,
                    fill: true,
                    pointRadius: 0,
                    tension: 0.25,
                    borderWidth: 1.75,
                }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        display: true,
                        ticks: { maxTicksLimit: 5, color: '#ADA697', font: { family: 'Public Sans Variable', size: 11 } },
                        grid: { color: '#EFEAE0' },
                    },
                    y: {
                        ticks: {
                            color: '#ADA697',
                            font: { family: 'JetBrains Mono Variable', size: 11 },
                            callback: (v) => (symbolAfter ? v + symbol : symbol + v),
                        },
                        grid: { color: '#EFEAE0' },
                    },
                },
            },
        });
    }
</script>
@endscript
