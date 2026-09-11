@php
    use App\Services\MarketData\MarketNumberFormatter;
    use Illuminate\Support\Str;

    $chart = is_array($coin->chart_7d) ? $coin->chart_7d : [];
    $labels = [];
    $values = [];
    foreach ($chart as $point) {
        if (! is_array($point) || count($point) < 2) {
            continue;
        }
        $labels[] = date('M j H:i', (int) ($point[0] / 1000));
        $values[] = (float) $point[1];
    }

    $change24 = $coin->percent_change_24h !== null ? (float) $coin->percent_change_24h : null;
    $chartUp = ($change24 ?? 0) >= 0;
    $volCap = null;
    if ($coin->market_cap !== null && (float) $coin->market_cap > 0 && $coin->volume_24h !== null) {
        $volCap = ((float) $coin->volume_24h / (float) $coin->market_cap) * 100;
    }
@endphp

<main data-afmc-page class="afmc-page afmc-page--detail">
    <a href="{{ route('home') }}" wire:navigate class="afmc-back">
        <span class="afmc-icon" style="font-size:16px">arrow_back</span>
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
                <span style="font:var(--type-body-sm);color:var(--text-faint);padding-bottom:4px">{{ __('24h · USD') }}</span>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:var(--space-4);align-items:start">
        <div style="display:grid;gap:var(--space-4)">
            <section class="afmc-card">
                <div class="afmc-card__body">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-3)">
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
                        ></canvas>
                    @else
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ __('Chart data will appear after the next detail sync.') }}</p>
                    @endif
                </div>
            </section>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--space-3)">
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
            </div>

            <div class="afmc-callout afmc-callout--note">
                <p class="afmc-callout__title">{{ __('Methodology') }}</p>
                <p class="afmc-callout__body">{{ __('Price and rankings are written from our primary market-data provider, with failover when that provider errors. Public pages read only from our database.') }}</p>
            </div>
        </div>

        <div style="display:grid;gap:var(--space-4)">
            <section class="afmc-card">
                <div class="afmc-card__header">
                    <div>
                        <span class="afmc-card__eyebrow">{{ __('Returns') }}</span>
                        <h2 class="afmc-card__title">{{ __('Performance') }}</h2>
                    </div>
                </div>
                <div class="afmc-card__body" style="display:grid;gap:var(--space-3)">
                    @foreach ([
                        ['1h', $coin->percent_change_1h],
                        ['24h', $coin->percent_change_24h],
                        ['7d', $coin->percent_change_7d],
                    ] as [$label, $value])
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <span style="font:var(--type-label);color:var(--text-muted)">{{ $label }}</span>
                            <x-afmc.price-change :value="$value !== null ? (float) $value : null" />
                        </div>
                    @endforeach
                </div>
            </section>

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
                            <div style="display:flex;justify-content:space-between;gap:var(--space-2)">
                                <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Circulating') }}</span>
                                <span style="font:var(--type-num)">{{ number_format((float) $coin->circulating_supply) }}</span>
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
                        ticks: { maxTicksLimit: 5, color: '#ADA697', font: { family: 'Public Sans', size: 11 } },
                        grid: { color: '#EFEAE0' },
                    },
                    y: {
                        ticks: {
                            color: '#ADA697',
                            font: { family: 'JetBrains Mono', size: 11 },
                            callback: (v) => '$' + v,
                        },
                        grid: { color: '#EFEAE0' },
                    },
                },
            },
        });
    }
</script>
@endscript
