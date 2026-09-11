<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\CoinDetailData;
use App\Services\MarketData\DTOs\GlobalMarketData;
use App\Services\MarketData\DTOs\MarketCoinData;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class MarketDataAggregator
{
    /** @var array<string, MarketDataProvider> */
    private array $providers;

    public function __construct(
        CoinGeckoProvider $coinGecko,
        CoinPaprikaProvider $coinPaprika,
    ) {
        $this->providers = [
            $coinGecko->name() => $coinGecko,
            $coinPaprika->name() => $coinPaprika,
        ];
    }

    public function primary(): MarketDataProvider
    {
        return $this->resolve((string) config('marketdata.primary', 'coingecko'));
    }

    public function failover(): MarketDataProvider
    {
        return $this->resolve((string) config('marketdata.failover', 'coinpaprika'));
    }

    public function resolve(string $name): MarketDataProvider
    {
        throw_unless(isset($this->providers[$name]), new RuntimeException("Unknown market data provider [{$name}]."));

        return $this->providers[$name];
    }

    /**
     * @return array{provider: string, coins: Collection<int, MarketCoinData>}
     */
    public function fetchMarketsWithFailover(int $page, int $perPage): array
    {
        return $this->withFailover(
            fn (MarketDataProvider $provider): Collection => $provider->fetchMarkets($page, $perPage),
            'coins',
        );
    }

    /**
     * @return array{provider: string, global: GlobalMarketData}
     */
    public function fetchGlobalWithFailover(): array
    {
        return $this->withFailover(
            fn (MarketDataProvider $provider): GlobalMarketData => $provider->fetchGlobal(),
            'global',
        );
    }

    public function fetchCoinDetail(string $providerName, string $externalId): CoinDetailData
    {
        return $this->resolve($providerName)->fetchCoinDetail($externalId);
    }

    /**
     * @template T
     *
     * @param  callable(MarketDataProvider): T  $callback
     * @return array{provider: string}&array<string, T>
     */
    private function withFailover(callable $callback, string $resultKey): array
    {
        $primary = $this->primary();
        $errors = [];

        try {
            return [
                'provider' => $primary->name(),
                $resultKey => $callback($primary),
            ];
        } catch (Throwable $exception) {
            $errors[] = $primary->name().': '.$exception->getMessage();
        }

        $failover = $this->failover();

        if ($failover->name() === $primary->name()) {
            throw new RuntimeException('Market data sync failed: '.implode(' | ', $errors));
        }

        try {
            return [
                'provider' => $failover->name(),
                $resultKey => $callback($failover),
            ];
        } catch (Throwable $exception) {
            $errors[] = $failover->name().': '.$exception->getMessage();
        }

        throw new RuntimeException('Market data sync failed: '.implode(' | ', $errors));
    }
}
