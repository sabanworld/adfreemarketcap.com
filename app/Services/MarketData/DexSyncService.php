<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Jobs\SyncDexPairDetail;
use App\Models\DexPair;
use App\Models\DexToken;
use App\Models\SyncRun;
use App\Services\MarketData\DTOs\DexPairData;
use Illuminate\Support\Collection;
use Throwable;

class DexSyncService
{
    /**
     * Matches decimal(12, 4) on dex_pairs.percent_change_24h. Fresh meme pools
     * often report trillion-percent pumps that would otherwise abort the sync.
     */
    private const PERCENT_CHANGE_MAX = 99_999_999.9999;

    public function __construct(
        private readonly DexDataProvider $provider,
        private readonly DexAuditStatusResolver $audit,
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
            $this->audit->warmListedLookup($pairs);

            $tokensByKey = $this->preloadTokens($pairs);
            $pairsByExternalId = $this->preloadPairs($pairs);
            $slugOwners = $this->preloadSlugOwners($pairs);

            $processed = 0;
            $failed = 0;
            $trendingIds = [];

            try {
                foreach ($pairs as $pair) {
                    try {
                        $model = $this->upsertPair($pair, $tokensByKey, $pairsByExternalId, $slugOwners);
                        $processed++;
                        if ($pair->isTrending && $model instanceof DexPair) {
                            $trendingIds[] = $model->id;
                        }
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                    }
                }
            } finally {
                $this->audit->clearListedLookup();
            }

            $this->prewarmTrendingDetails($trendingIds);

            $message = "Synced {$processed} DEX pairs.";
            if ($failed > 0) {
                $message .= " Failed {$failed} after write errors.";
            }

            $run->markSucceeded($processed, $message);

            return $run->fresh();
        } catch (Throwable $throwable) {
            $this->audit->clearListedLookup();
            $run->markFailed($throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * @return Collection<int, DexPairData>
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
                        networkId: $pair->networkId ?? $existing->networkId,
                        contractAddress: $pair->contractAddress ?? $existing->contractAddress,
                        baseTokenAddress: $pair->baseTokenAddress ?? $existing->baseTokenAddress,
                        quoteTokenAddress: $pair->quoteTokenAddress ?? $existing->quoteTokenAddress,
                        baseTokenName: $pair->baseTokenName ?? $existing->baseTokenName,
                        coingeckoCoinId: $pair->coingeckoCoinId ?? $existing->coingeckoCoinId,
                        auditStatus: $pair->auditStatus,
                        price: $pair->price,
                        percentChange24h: $pair->percentChange24h,
                        liquidityUsd: $pair->liquidityUsd,
                        volume24h: $pair->volume24h,
                        volume1h: $pair->volume1h ?? $existing->volume1h,
                        volume6h: $pair->volume6h ?? $existing->volume6h,
                        fdvUsd: $pair->fdvUsd ?? $existing->fdvUsd,
                        marketCapUsd: $pair->marketCapUsd ?? $existing->marketCapUsd,
                        txns24h: $pair->txns24h,
                        buys24h: $pair->buys24h ?? $existing->buys24h,
                        sells24h: $pair->sells24h ?? $existing->sells24h,
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

    /**
     * @param  Collection<int, DexPairData>  $pairs
     * @return Collection<string, DexToken>
     */
    private function preloadTokens(Collection $pairs): Collection
    {
        /** @var Collection<string, DexToken> $tokens */
        $tokens = collect();

        $byNetwork = $pairs
            ->filter(fn (DexPairData $pair): bool => filled($pair->networkId) && filled($pair->baseTokenAddress))
            ->groupBy(fn (DexPairData $pair): string => (string) $pair->networkId);

        foreach ($byNetwork as $networkId => $group) {
            $addresses = $group
                ->map(fn (DexPairData $pair): string => (string) $pair->baseTokenAddress)
                ->unique()
                ->values()
                ->all();

            if ($addresses === []) {
                continue;
            }

            foreach (
                DexToken::query()
                    ->where('network_id', $networkId)
                    ->whereIn('address', $addresses)
                    ->get() as $token
            ) {
                $tokens->put($this->tokenKey($token->network_id, $token->address), $token);
            }
        }

        return $tokens;
    }

    /**
     * @param  Collection<int, DexPairData>  $pairs
     * @return Collection<string, DexPair>
     */
    private function preloadPairs(Collection $pairs): Collection
    {
        $externalIds = $pairs->map(fn (DexPairData $pair): string => $pair->externalId)->all();

        if ($externalIds === []) {
            return collect();
        }

        return DexPair::query()
            ->where('provider', $this->provider->name())
            ->whereIn('external_id', $externalIds)
            ->get()
            ->keyBy('external_id');
    }

    /**
     * @param  Collection<int, DexPairData>  $pairs
     * @return Collection<string, array{provider: string, external_id: string}>
     */
    private function preloadSlugOwners(Collection $pairs): Collection
    {
        $candidateSlugs = $pairs
            ->map(fn (DexPairData $pair): string => DexPair::makeSlug($pair->pair, $pair->chain, $pair->dex))
            ->unique()
            ->values()
            ->all();

        if ($candidateSlugs === []) {
            return collect();
        }

        return DexPair::query()
            ->whereIn('slug', $candidateSlugs)
            ->get(['slug', 'provider', 'external_id'])
            ->mapWithKeys(fn (DexPair $pair): array => [
                $pair->slug => [
                    'provider' => (string) $pair->provider,
                    'external_id' => (string) $pair->external_id,
                ],
            ]);
    }

    /**
     * @param  Collection<string, DexToken>  $tokensByKey
     * @param  Collection<string, DexPair>  $pairsByExternalId
     * @param  Collection<string, array{provider: string, external_id: string}>  $slugOwners
     */
    private function upsertPair(
        DexPairData $pair,
        Collection $tokensByKey,
        Collection $pairsByExternalId,
        Collection $slugOwners,
    ): DexPair {
        $slug = $this->slugFor($pair, $slugOwners);
        $tokenId = $this->upsertBaseToken($pair, $tokensByKey)?->id;

        $attributes = [
            'slug' => $slug,
            'pair' => $pair->pair,
            'base_symbol' => $pair->baseSymbol,
            'quote_symbol' => $pair->quoteSymbol,
            'dex' => $pair->dex,
            'chain' => $pair->chain,
            'network_id' => $pair->networkId,
            'contract_address' => $pair->contractAddress,
            'base_token_address' => $pair->baseTokenAddress,
            'quote_token_address' => $pair->quoteTokenAddress,
            'dex_token_id' => $tokenId,
            'audit_status' => $this->audit->resolveFromPairData($pair),
            'price' => $pair->price,
            'percent_change_24h' => $this->percentForStorage($pair->percentChange24h),
            'liquidity_usd' => $pair->liquidityUsd,
            'volume_24h' => $pair->volume24h,
            'volume_1h' => $pair->volume1h,
            'volume_6h' => $pair->volume6h,
            'fdv_usd' => $pair->fdvUsd,
            'market_cap_usd' => $pair->marketCapUsd,
            'txns_24h' => $pair->txns24h,
            'buys_24h' => $pair->buys24h,
            'sells_24h' => $pair->sells24h,
            'paired_at' => $pair->pairedAt,
            'is_trending' => $pair->isTrending,
            'rank' => $pair->rank,
            'synced_at' => now(),
        ];

        $existing = $pairsByExternalId->get($pair->externalId);
        if ($existing instanceof DexPair) {
            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }

        $created = DexPair::query()->create([
            'provider' => $this->provider->name(),
            'external_id' => $pair->externalId,
            ...$attributes,
        ]);

        $pairsByExternalId->put($pair->externalId, $created);
        $slugOwners->put($slug, [
            'provider' => $this->provider->name(),
            'external_id' => $pair->externalId,
        ]);

        return $created;
    }

    /**
     * @param  Collection<string, DexToken>  $tokensByKey
     */
    private function upsertBaseToken(DexPairData $pair, Collection $tokensByKey): ?DexToken
    {
        if (! filled($pair->networkId) || ! filled($pair->baseTokenAddress)) {
            return null;
        }

        $attributes = [
            'symbol' => $pair->baseSymbol,
            'name' => $pair->baseTokenName,
            'coingecko_coin_id' => $pair->coingeckoCoinId,
            'price' => $pair->price,
            'percent_change_24h' => $this->percentForStorage($pair->percentChange24h),
            'fdv_usd' => $pair->fdvUsd,
            'market_cap_usd' => $pair->marketCapUsd,
            'liquidity_usd' => $pair->liquidityUsd,
            'volume_24h' => $pair->volume24h,
            'synced_at' => now(),
        ];

        $key = $this->tokenKey($pair->networkId, $pair->baseTokenAddress);
        $existing = $tokensByKey->get($key);

        if ($existing instanceof DexToken) {
            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }

        $created = DexToken::query()->create([
            'network_id' => $pair->networkId,
            'address' => $pair->baseTokenAddress,
            ...$attributes,
        ]);

        $tokensByKey->put($key, $created);

        return $created;
    }

    /**
     * @param  list<int>  $pairIds
     */
    private function prewarmTrendingDetails(array $pairIds): void
    {
        $limit = max(0, (int) config('marketdata.sync.dex_detail_prewarm', 5));
        if ($limit === 0 || $pairIds === []) {
            return;
        }

        foreach (array_slice(array_values(array_unique($pairIds)), 0, $limit) as $pairId) {
            SyncDexPairDetail::dispatch($pairId);
        }
    }

    private function percentForStorage(?float $value): ?float
    {
        if ($value === null || ! is_finite($value)) {
            return null;
        }

        return max(-self::PERCENT_CHANGE_MAX, min(self::PERCENT_CHANGE_MAX, $value));
    }

    /**
     * @param  Collection<string, array{provider: string, external_id: string}>  $slugOwners
     */
    private function slugFor(DexPairData $pair, Collection $slugOwners): string
    {
        $slug = DexPair::makeSlug($pair->pair, $pair->chain, $pair->dex);
        $owner = $slugOwners->get($slug);

        if ($owner === null
            || ($owner['provider'] === $this->provider->name() && $owner['external_id'] === $pair->externalId)) {
            $slugOwners->put($slug, [
                'provider' => $this->provider->name(),
                'external_id' => $pair->externalId,
            ]);

            return $slug;
        }

        $suffixed = $slug . '-' . substr(sha1($pair->externalId), 0, 6);
        $slugOwners->put($suffixed, [
            'provider' => $this->provider->name(),
            'external_id' => $pair->externalId,
        ]);

        return $suffixed;
    }

    private function tokenKey(string $networkId, string $address): string
    {
        return $networkId . '|' . $address;
    }
}
