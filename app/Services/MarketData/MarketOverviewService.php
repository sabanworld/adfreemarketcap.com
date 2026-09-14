<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\MarketGlobal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The global market figures, and which surface owns each of them.
 *
 * Every number has one owner on a page. The markets status strip publishes total market cap,
 * 24h volume and the dominance split, and the "All coins" tab badge carries the tracked count,
 * so the ticker asks this service what is left rather than keeping its own list of exceptions.
 * Ownership is derived from what the strip actually renders: adding a metric to the strip
 * removes it from the ticker instead of leaving two renderings to drift apart in precision.
 */
class MarketOverviewService
{
    public const DOMINANCE_CACHE_KEY = 'market_overview.dominance';

    public const DOMINANCE_CACHE_SECONDS = 60;

    public const CAP_TREND_CACHE_KEY = 'market_overview.cap_trend';

    public const CAP_TREND_CACHE_SECONDS = 120;

    /**
     * The market-cap panel's line and the percentage printed beside it, read from one query so
     * they cannot disagree. A chart must never contradict its own number, and the provider's
     * own 24h change figure does: it is a like-for-like reading, while the total we snapshot
     * also moves when the provider lists an asset. Publishing both put a red percentage next to
     * a visibly rising line, so the panel states the change in the series it actually draws.
     *
     * Fewer than two snapshots means no line and no percentage, because an invented point would
     * be a chart we cannot source.
     *
     * @return array{series: list<float>, change: float|null}
     */
    public function marketCapTrend(int $hours = 24, int $points = 24): array
    {
        $hours = max(1, $hours);
        $points = max(2, $points);

        /** @var array{series: list<float>, change: float|null} $trend */
        $trend = Cache::remember(
            self::CAP_TREND_CACHE_KEY . ":{$hours}:{$points}",
            self::CAP_TREND_CACHE_SECONDS,
            function () use ($hours, $points): array {
                $snapshots = MarketGlobal::query()
                    ->whereNotNull('total_market_cap')
                    ->where('synced_at', '>=', Carbon::now()->subHours($hours))
                    ->orderBy('synced_at')
                    ->pluck('total_market_cap')
                    ->map(static fn (mixed $cap): float => (float) $cap)
                    ->filter(static fn (float $cap): bool => $cap > 0)
                    ->values();

                if ($snapshots->count() < 2) {
                    return ['series' => [], 'change' => null];
                }

                $series = $this->downsample($snapshots->all(), $points);
                $first = $series[0];
                $last = $series[count($series) - 1];

                return [
                    'series' => $series,
                    'change' => ($last - $first) / $first * 100,
                ];
            },
        );

        return $trend;
    }

    /**
     * Share of total market cap held by Bitcoin, Ethereum and everything else. Bitcoin comes
     * from the provider's own dominance figure so the strip and the provider agree; Ethereum
     * is derived from the ranked table, which is the only place we hold its market cap.
     *
     * @return list<array{label: string, percent: float}>
     */
    public function dominanceSplit(): array
    {
        /** @var list<array{label: string, percent: float}> $split */
        $split = Cache::remember(self::DOMINANCE_CACHE_KEY, self::DOMINANCE_CACHE_SECONDS, function (): array {
            $global = MarketGlobal::latestSnapshot();
            $totalCap = $global?->total_market_cap !== null ? (float) $global->total_market_cap : null;
            $btc = $global?->btc_dominance !== null ? (float) $global->btc_dominance : null;

            if ($btc === null || $btc <= 0 || $totalCap === null || $totalCap <= 0) {
                return [];
            }

            $ethCap = (float) (Coin::query()->where('slug', 'ethereum')->value('market_cap') ?? 0);

            // No Ethereum row means no second share, and a two-part split labelled BTC / Other
            // would state a figure we cannot source. The ticker keeps dominance in that case.
            if ($ethCap <= 0) {
                return [];
            }

            $eth = $ethCap / $totalCap * 100;
            $rest = 100 - $btc - $eth;

            if ($rest <= 0) {
                return [];
            }

            return [
                ['label' => 'BTC', 'percent' => round($btc, 1)],
                ['label' => 'ETH', 'percent' => round($eth, 1)],
                ['label' => 'Other', 'percent' => round($rest, 1)],
            ];
        });

        return $split;
    }

    /**
     * Labels the markets status strip and its tab badge publish themselves.
     *
     * @return list<string>
     */
    public function statusStripOwns(): array
    {
        $global = MarketGlobal::latestSnapshot();

        $owned = [];

        if ($global?->total_market_cap !== null) {
            $owned[] = 'market_cap';
        }

        if ($global?->total_volume_24h !== null) {
            $owned[] = 'volume_24h';
        }

        if ($this->dominanceSplit() !== []) {
            $owned[] = 'btc_dominance';
        }

        // The "All coins" tab badge carries the tracked count on the markets view.
        $owned[] = 'assets_tracked';

        return $owned;
    }

    /**
     * The strip metrics a page does not already publish itself. An empty list means the ticker
     * has nothing of its own to say, so it is hidden rather than padded out.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public function tickerItems(bool $statusStripOnPage = false): array
    {
        $global = MarketGlobal::latestSnapshot();
        $owned = $statusStripOnPage ? $this->statusStripOwns() : [];

        $items = [
            [
                'key' => 'market_cap',
                'label' => __('Market cap'),
                'value' => MarketNumberFormatter::money($global?->total_market_cap !== null ? (float) $global->total_market_cap : null),
            ],
            [
                'key' => 'volume_24h',
                'label' => __('24h volume'),
                'value' => MarketNumberFormatter::money($global?->total_volume_24h !== null ? (float) $global->total_volume_24h : null),
            ],
            [
                'key' => 'btc_dominance',
                'label' => __('BTC dominance'),
                'value' => MarketNumberFormatter::percent($global?->btc_dominance !== null ? (float) $global->btc_dominance : null),
            ],
            [
                'key' => 'assets_tracked',
                'label' => __('Assets tracked'),
                'value' => number_format(Coin::rankedCount()),
            ],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => ! in_array($item['key'], $owned, true),
        ));
    }

    public function forgetCaches(): void
    {
        Cache::forget(self::DOMINANCE_CACHE_KEY);
    }

    /**
     * Keep the first and last reading and spread the rest evenly: the endpoints carry the
     * direction the percentage beside the line states, so neither may be dropped.
     *
     * @param  list<float>  $values
     * @return list<float>
     */
    private function downsample(array $values, int $points): array
    {
        $count = count($values);

        if ($count <= $points) {
            return $values;
        }

        $sampled = [];

        for ($i = 0; $i < $points; $i++) {
            $sampled[] = $values[(int) round($i * ($count - 1) / ($points - 1))];
        }

        return $sampled;
    }
}
