<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\PercentChangeData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Crypto APIs serves CoinMarketCap market data. It is not a ranking source
 * here: only the 1h and 7d percentages are read from it, because CoinGecko
 * rounds those to 0.1 on /coins/markets and a quiet hour then reads as 0.00%.
 */
class CryptoApisProvider
{
    /**
     * The list endpoint refuses a larger page.
     */
    private const MAX_PER_PAGE = 50;

    public function isConfigured(): bool
    {
        return filled(config('marketdata.cryptoapis.api_key'));
    }

    /**
     * Assets come back ranked by market cap, so the first pages cover the same
     * coins our ranking does. A ticker two different assets share is dropped
     * rather than guessed at, which keeps it off the wrong coin. One asset
     * arriving on two pages is not a conflict, because the windows overlap.
     *
     * Pages are spaced and 429s are retried, because Crypto APIs meters credits
     * per second and rejects a burst with throughput_limit_reached. A later
     * page that still fails after retries keeps whatever earlier pages returned.
     *
     * @return Collection<string, PercentChangeData>
     */
    public function fetchPercentChangesBySymbol(int $coins): Collection
    {
        $perPage = $this->perPage();
        $pages = $this->pages($coins, $perPage);

        $items = [];

        for ($page = 0; $page < $pages; $page++) {
            if ($page > 0) {
                $this->pauseBetweenPages();
            }

            try {
                foreach ($this->fetchPage($perPage, $page * $perPage) as $item) {
                    $items[] = $item;
                }
            } catch (Throwable $exception) {
                if ($items === []) {
                    throw $exception;
                }

                report($exception);

                break;
            }
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['symbol'] ?? null) && $item['symbol'] !== '')
            ->mapToGroups(fn (array $item): array => [Str::upper($item['symbol']) => $item])
            ->reject(fn (Collection $assets): bool => $this->isSharedTicker($assets))
            ->map(fn (Collection $assets): PercentChangeData => $this->percentChanges($assets->first()))
            ->reject(fn (PercentChangeData $changes): bool => $changes->isEmpty());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $assets
     */
    private function isSharedTicker(Collection $assets): bool
    {
        return $assets
            ->map(fn (array $asset, int $index): string => $this->assetId($asset) ?? 'unidentified:' . $index)
            ->unique()
            ->count() !== 1;
    }

    /**
     * Tells two assets apart when they share a ticker, and recognises one asset
     * that two overlapping pages both returned.
     *
     * @param  array<string, mixed>  $asset
     */
    private function assetId(array $asset): ?string
    {
        foreach (['referenceId', 'slug'] as $key) {
            $value = $asset[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * The two rankings drift apart the further down the list you read, so the
     * window runs a page deeper than the coins being synced. Coins past it keep
     * the CoinGecko percentages, and the ceiling keeps the call count fixed.
     */
    private function pages(int $coins, int $perPage): int
    {
        $ceiling = max(1, (int) config('marketdata.cryptoapis.max_pages', 5));

        return min((int) ceil(max(1, $coins) / $perPage) + 1, $ceiling);
    }

    /**
     * @return array<int, mixed>
     */
    private function fetchPage(int $limit, int $offset): array
    {
        $response = $this->client()->get('/market-data/metadata/assets', [
            'limit' => $limit,
            'offset' => $offset,
            'type' => 'crypto',
        ]);

        throw_unless($response->successful(), new RuntimeException(
            'Crypto APIs assets failed: ' . $response->status() . ' ' . $response->body()
        ));

        $items = $response->json('data.items');

        return is_array($items) ? $items : [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function percentChanges(array $item): PercentChangeData
    {
        return new PercentChangeData(
            percentChange1h: $this->floatOrNull(data_get($item, 'specificData.1HourPriceChangeInPercentage')),
            percentChange7d: $this->floatOrNull(data_get($item, 'specificData.1WeekPriceChangeInPercentage')),
        );
    }

    /**
     * Percentages arrive as strings, and an asset without a market sends null.
     */
    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function perPage(): int
    {
        $perPage = (int) config('marketdata.cryptoapis.per_page', self::MAX_PER_PAGE);

        return max(1, min(self::MAX_PER_PAGE, $perPage));
    }

    private function pauseBetweenPages(): void
    {
        $delayMs = max(0, (int) config('marketdata.cryptoapis.page_delay_ms', 250));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    private function client(): PendingRequest
    {
        $retries = max(1, (int) config('marketdata.cryptoapis.retry_times', 4));
        $baseSleep = max(0, (int) config('marketdata.cryptoapis.retry_sleep_ms', 250));

        return app(ProviderCallCounter::class)->count(
            Http::baseUrl((string) config('marketdata.cryptoapis.base_url'))
                ->withHeaders(['X-API-Key' => (string) config('marketdata.cryptoapis.api_key')])
                ->acceptJson()
                ->timeout(30)
                ->retry(
                    $retries,
                    fn (int $attempt): int => $baseSleep * $attempt,
                    fn (Throwable $exception): bool => $exception instanceof RequestException
                        && $exception->response?->status() === 429,
                    throw: false,
                ),
            ProviderCallCounter::CRYPTOAPIS,
        );
    }
}
