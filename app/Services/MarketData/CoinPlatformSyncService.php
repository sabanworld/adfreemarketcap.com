<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\CoinPlatform;
use App\Models\CoinProviderId;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoinPlatformSyncService
{
    public function __construct(
        private readonly CoinGeckoProvider $coingecko,
    ) {}

    public function sync(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'coin_platforms',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'coingecko',
            'started_at' => now(),
        ]);

        try {
            $rows = $this->coingecko->fetchCoinPlatforms();
            $externalIds = array_column($rows, 'external_id');

            $providerMap = CoinProviderId::query()
                ->where('provider', 'coingecko')
                ->whereIn('external_id', $externalIds)
                ->pluck('coin_id', 'external_id');

            $processed = 0;
            $now = now();
            /** @var array<int, true> $coinIds */
            $coinIds = [];
            /** @var list<array{coin_id: int, platform_id: string, contract_address: string, created_at: mixed, updated_at: mixed}> $insertRows */
            $insertRows = [];

            foreach ($rows as $row) {
                $coinId = $providerMap->get($row['external_id']);
                if ($coinId === null) {
                    continue;
                }

                $coinId = (int) $coinId;
                $coinIds[$coinId] = true;

                foreach ($row['platforms'] as $platformId => $contract) {
                    $insertRows[] = [
                        'coin_id' => $coinId,
                        'platform_id' => $platformId,
                        'contract_address' => $contract,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $processed++;
                }
            }

            DB::transaction(function () use ($coinIds, $insertRows): void {
                $ids = array_keys($coinIds);
                if ($ids !== []) {
                    CoinPlatform::query()->whereIn('coin_id', $ids)->delete();
                }

                foreach (array_chunk($insertRows, 500) as $chunk) {
                    CoinPlatform::query()->insert($chunk);
                }
            });

            $run->markSucceeded($processed, 'Synced coin network platforms from CoinGecko.');

            app(NetworkCatalogService::class)->forgetAvailableCache();

            return $run->fresh();
        } catch (Throwable $throwable) {
            $run->markFailed($throwable->getMessage());

            throw $throwable;
        }
    }
}
