<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\DexPair;
use App\Models\SyncRun;
use App\Services\MarketData\DTOs\DexPairData;
use Illuminate\Support\Collection;
use Throwable;

class DexSyncService
{
    public function __construct(
        private readonly DexDataProvider $provider,
    ) {}

    public function syncPairs(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'dex_pairs',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => $this->provider->name(),
            'started_at' => now(),
        ]);

        try {
            $pairs = $this->collectPairs();
            $processed = 0;

            foreach ($pairs as $pair) {
                $this->upsertPair($pair);
                $processed++;
            }

            $run->markSucceeded($processed, "Synced {$processed} DEX pairs.");

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @return Collection<string, DexPairData>
     */
    private function collectPairs(): Collection
    {
        /** @var Collection<string, DexPairData> $merged */
        $merged = collect();

        $trendingPages = max(1, (int) config('marketdata.sync.dex_trending_pages', 1));
        for ($page = 1; $page <= $trendingPages; $page++) {
            foreach ($this->provider->fetchTrendingPools($page) as $pair) {
                $merged->put($pair->externalId, $pair);
            }
        }

        $newPages = max(0, (int) config('marketdata.sync.dex_new_pages', 1));
        for ($page = 1; $page <= $newPages; $page++) {
            foreach ($this->provider->fetchNewPools($page) as $pair) {
                $existing = $merged->get($pair->externalId);
                if ($existing instanceof DexPairData) {
                    $merged->put($pair->externalId, new DexPairData(
                        externalId: $existing->externalId,
                        pair: $pair->pair,
                        baseSymbol: $pair->baseSymbol,
                        quoteSymbol: $pair->quoteSymbol,
                        dex: $pair->dex,
                        chain: $pair->chain,
                        contractAddress: $pair->contractAddress,
                        auditStatus: $pair->auditStatus,
                        price: $pair->price,
                        percentChange24h: $pair->percentChange24h,
                        liquidityUsd: $pair->liquidityUsd,
                        volume24h: $pair->volume24h,
                        txns24h: $pair->txns24h,
                        pairedAt: $pair->pairedAt ?? $existing->pairedAt,
                        isTrending: true,
                        rank: $existing->rank,
                    ));

                    continue;
                }

                $merged->put($pair->externalId, $pair);
            }
        }

        $networks = (array) config('marketdata.sync.dex_networks', []);
        foreach ($networks as $network) {
            if (! is_string($network) || $network === '') {
                continue;
            }

            foreach ($this->provider->fetchNetworkPools($network, 1) as $pair) {
                if ($merged->has($pair->externalId)) {
                    continue;
                }

                $merged->put($pair->externalId, $pair);
            }
        }

        return $merged->values();
    }

    private function upsertPair(DexPairData $pair): void
    {
        $slug = $this->slugFor($pair);

        DexPair::query()->updateOrCreate(
            [
                'provider' => $this->provider->name(),
                'external_id' => $pair->externalId,
            ],
            [
                'slug' => $slug,
                'pair' => $pair->pair,
                'base_symbol' => $pair->baseSymbol,
                'quote_symbol' => $pair->quoteSymbol,
                'dex' => $pair->dex,
                'chain' => $pair->chain,
                'contract_address' => $pair->contractAddress,
                'audit_status' => $pair->auditStatus,
                'price' => $pair->price,
                'percent_change_24h' => $pair->percentChange24h,
                'liquidity_usd' => $pair->liquidityUsd,
                'volume_24h' => $pair->volume24h,
                'txns_24h' => $pair->txns24h,
                'paired_at' => $pair->pairedAt,
                'is_trending' => $pair->isTrending,
                'rank' => $pair->rank,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * Launchpads mint many tokens under the same symbol, so one chain and DEX
     * can carry several pools that all read as "THERSOL / SOL". The slug is a
     * route key, so keep it readable but suffix it when another pool already
     * holds it. The suffix comes from the pool id, so it stays put across runs.
     */
    private function slugFor(DexPairData $pair): string
    {
        $slug = DexPair::makeSlug($pair->pair, $pair->chain, $pair->dex);

        $takenByAnotherPool = DexPair::query()
            ->where('slug', $slug)
            ->whereNot(function ($query) use ($pair): void {
                $query->where('provider', $this->provider->name())
                    ->where('external_id', $pair->externalId);
            })
            ->exists();

        if (! $takenByAnotherPool) {
            return $slug;
        }

        return $slug . '-' . substr(sha1($pair->externalId), 0, 6);
    }
}
