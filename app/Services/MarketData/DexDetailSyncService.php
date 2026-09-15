<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\DexPair;
use App\Models\DexToken;
use App\Models\DexTokenHolder;
use App\Models\DexTrade;
use App\Models\SyncRun;
use App\Services\MarketData\DTOs\DexHolderData;
use App\Services\MarketData\DTOs\DexPairData;
use App\Services\MarketData\DTOs\DexTradeData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DexDetailSyncService
{
    private const PERCENT_CHANGE_MAX = 99_999_999.9999;

    public function __construct(
        private readonly DexDataProvider $provider,
        private readonly DexChartService $charts,
        private readonly DexAuditStatusResolver $audit,
    ) {}

    public function syncPair(DexPair $pair): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'dex_pair_detail',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => $this->provider->name(),
            'started_at' => now(),
        ]);

        try {
            if ($pair->provider === 'seed') {
                $pair->forceFill([
                    'network_id' => DexNetwork::resolve($pair->network_id, $pair->chain, $pair->external_id),
                    'detail_synced_at' => now(),
                    'trades_synced_at' => now(),
                ])->save();

                $run->markSucceeded(1, 'Seed DEX pair detail uses offline demo data for ' . $pair->slug);

                return $run->fresh();
            }

            $network = DexNetwork::resolve($pair->network_id, $pair->chain, $pair->external_id) ?? '';
            $poolAddress = $pair->poolAddress() ?? '';
            throw_unless($network !== '' && $poolAddress !== '', new RuntimeException(
                'Dex pair is missing network_id or pool address.'
            ));

            if ($pair->network_id !== $network) {
                $pair->forceFill(['network_id' => $network])->save();
            }

            $detail = $this->provider->fetchPoolDetail($network, $poolAddress);
            $this->persistPairData($pair, $detail->pair);
            $this->replacePairTrades($pair, $detail->trades);
            $this->charts->upsertSeriesFor($pair, $detail->chartSeries);

            $pair->forceFill([
                'detail_synced_at' => now(),
                'trades_synced_at' => now(),
            ])->save();

            $run->markSucceeded(1, 'Synced DEX pair detail for ' . $pair->slug);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    public function syncToken(DexToken $token): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'dex_token_detail',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => $this->provider->name(),
            'started_at' => now(),
        ]);

        try {
            $detail = $this->provider->fetchTokenDetail($token->network_id, $token->address);

            $token->forceFill([
                'symbol' => $detail->symbol,
                'name' => $detail->name,
                'coingecko_coin_id' => $detail->coingeckoCoinId,
                'price' => $detail->price,
                'percent_change_24h' => $this->percentForStorage($detail->percentChange24h),
                'fdv_usd' => $detail->fdvUsd,
                'market_cap_usd' => $detail->marketCapUsd,
                'liquidity_usd' => $detail->liquidityUsd,
                'volume_24h' => $detail->volume24h,
                'holders_count' => $detail->holdersCount,
                'detail_synced_at' => now(),
                'trades_synced_at' => now(),
                'synced_at' => now(),
            ])->save();

            foreach ($detail->pools as $pool) {
                $this->upsertRelatedPool($token, $pool);
            }

            $this->replaceTokenTrades($token, $detail->trades);
            $this->charts->upsertSeriesFor($token, $detail->chartSeries);

            if (! $detail->holdersUnavailable) {
                $this->replaceHolders($token, $detail->holders);
                $token->forceFill(['holders_synced_at' => now()])->save();
            }

            $run->markSucceeded(1, 'Synced DEX token detail for ' . $token->network_id . '/' . $token->address);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    private function persistPairData(DexPair $pair, DexPairData $data): void
    {
        $token = null;
        if (filled($data->networkId) && filled($data->baseTokenAddress)) {
            $token = DexToken::query()->updateOrCreate(
                [
                    'network_id' => $data->networkId,
                    'address' => $data->baseTokenAddress,
                ],
                [
                    'symbol' => $data->baseSymbol,
                    'name' => $data->baseTokenName,
                    'coingecko_coin_id' => $data->coingeckoCoinId,
                    'price' => $data->price,
                    'percent_change_24h' => $this->percentForStorage($data->percentChange24h),
                    'fdv_usd' => $data->fdvUsd,
                    'market_cap_usd' => $data->marketCapUsd,
                    'liquidity_usd' => $data->liquidityUsd,
                    'volume_24h' => $data->volume24h,
                    'synced_at' => now(),
                ],
            );
        }

        $pair->forceFill([
            'pair' => $data->pair,
            'base_symbol' => $data->baseSymbol,
            'quote_symbol' => $data->quoteSymbol,
            'dex' => $data->dex,
            'chain' => $data->chain,
            'network_id' => $data->networkId ?? $pair->network_id,
            'contract_address' => $data->contractAddress ?? $pair->contract_address,
            'base_token_address' => $data->baseTokenAddress ?? $pair->base_token_address,
            'quote_token_address' => $data->quoteTokenAddress ?? $pair->quote_token_address,
            'dex_token_id' => $token?->id ?? $pair->dex_token_id,
            'audit_status' => $this->audit->resolveFromPairData($data),
            'price' => $data->price,
            'percent_change_24h' => $this->percentForStorage($data->percentChange24h),
            'liquidity_usd' => $data->liquidityUsd,
            'volume_24h' => $data->volume24h,
            'volume_1h' => $data->volume1h,
            'volume_6h' => $data->volume6h,
            'fdv_usd' => $data->fdvUsd,
            'market_cap_usd' => $data->marketCapUsd,
            'txns_24h' => $data->txns24h,
            'buys_24h' => $data->buys24h,
            'sells_24h' => $data->sells24h,
            'paired_at' => $data->pairedAt ?? $pair->paired_at,
            'synced_at' => now(),
        ])->save();
    }

    private function upsertRelatedPool(DexToken $token, DexPairData $data): void
    {
        $slug = DexPair::makeSlug($data->pair, $data->chain, $data->dex);
        $taken = DexPair::query()
            ->where('slug', $slug)
            ->where(function ($query) use ($data): void {
                $query->where('provider', '!=', $this->provider->name())
                    ->orWhere('external_id', '!=', $data->externalId);
            })
            ->exists();

        if ($taken) {
            $slug .= '-' . substr(sha1($data->externalId), 0, 6);
        }

        DexPair::query()->updateOrCreate(
            [
                'provider' => $this->provider->name(),
                'external_id' => $data->externalId,
            ],
            [
                'slug' => $slug,
                'pair' => $data->pair,
                'base_symbol' => $data->baseSymbol,
                'quote_symbol' => $data->quoteSymbol,
                'dex' => $data->dex,
                'chain' => $data->chain,
                'network_id' => $data->networkId ?? $token->network_id,
                'contract_address' => $data->contractAddress,
                'base_token_address' => $data->baseTokenAddress ?? $token->address,
                'quote_token_address' => $data->quoteTokenAddress,
                'dex_token_id' => $token->id,
                'audit_status' => $this->audit->resolveFromPairData($data),
                'price' => $data->price,
                'percent_change_24h' => $this->percentForStorage($data->percentChange24h),
                'liquidity_usd' => $data->liquidityUsd,
                'volume_24h' => $data->volume24h,
                'volume_1h' => $data->volume1h,
                'volume_6h' => $data->volume6h,
                'fdv_usd' => $data->fdvUsd,
                'market_cap_usd' => $data->marketCapUsd,
                'txns_24h' => $data->txns24h,
                'buys_24h' => $data->buys24h,
                'sells_24h' => $data->sells24h,
                'paired_at' => $data->pairedAt,
                'is_trending' => $data->isTrending,
                'rank' => $data->rank,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * @param  list<DexTradeData>  $trades
     */
    private function replacePairTrades(DexPair $pair, array $trades): void
    {
        DB::transaction(function () use ($pair, $trades): void {
            DexTrade::query()->where('dex_pair_id', $pair->id)->delete();

            foreach ($trades as $trade) {
                DexTrade::query()->create([
                    'dex_pair_id' => $pair->id,
                    'dex_token_id' => $pair->dex_token_id,
                    ...$this->tradeAttributes($trade),
                ]);
            }
        });
    }

    /**
     * @param  list<DexTradeData>  $trades
     */
    private function replaceTokenTrades(DexToken $token, array $trades): void
    {
        DB::transaction(function () use ($token, $trades): void {
            DexTrade::query()->where('dex_token_id', $token->id)->whereNull('dex_pair_id')->delete();

            foreach ($trades as $trade) {
                DexTrade::query()->create([
                    'dex_token_id' => $token->id,
                    ...$this->tradeAttributes($trade),
                ]);
            }
        });
    }

    /**
     * @param  list<DexHolderData>  $holders
     */
    private function replaceHolders(DexToken $token, array $holders): void
    {
        DB::transaction(function () use ($token, $holders): void {
            DexTokenHolder::query()->where('dex_token_id', $token->id)->delete();

            foreach ($holders as $holder) {
                DexTokenHolder::query()->create([
                    'dex_token_id' => $token->id,
                    'rank' => $holder->rank,
                    'address' => $holder->address,
                    'label' => $holder->label,
                    'amount' => $holder->amount,
                    'percentage' => $holder->percentage,
                    'value_usd' => $holder->valueUsd,
                    'explorer_url' => $holder->explorerUrl,
                ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function tradeAttributes(DexTradeData $trade): array
    {
        $tradedAt = null;
        if (filled($trade->tradedAt)) {
            try {
                $tradedAt = Carbon::parse($trade->tradedAt);
            } catch (Throwable) {
                $tradedAt = null;
            }
        }

        return [
            'tx_hash' => $trade->txHash,
            'kind' => $trade->kind,
            'price_usd' => $trade->priceUsd,
            'volume_usd' => $trade->volumeUsd,
            'from_token_amount' => $trade->fromTokenAmount,
            'to_token_amount' => $trade->toTokenAmount,
            'trader_address' => $trade->traderAddress,
            'traded_at' => $tradedAt,
        ];
    }

    private function percentForStorage(?float $value): ?float
    {
        if ($value === null || ! is_finite($value)) {
            return null;
        }

        return max(-self::PERCENT_CHANGE_MAX, min(self::PERCENT_CHANGE_MAX, $value));
    }
}
