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

            DB::transaction(function () use ($rows, $providerMap, &$processed): void {
                foreach ($rows as $row) {
                    $coinId = $providerMap->get($row['external_id']);
                    if ($coinId === null) {
                        continue;
                    }

                    $coinId = (int) $coinId;
                    $platformIds = [];

                    foreach ($row['platforms'] as $platformId => $contract) {
                        $platformIds[] = $platformId;
                        CoinPlatform::query()->updateOrCreate(
                            [
                                'coin_id' => $coinId,
                                'platform_id' => $platformId,
                            ],
                            [
                                'contract_address' => $contract,
                            ],
                        );
                        $processed++;
                    }

                    $deleteQuery = CoinPlatform::query()->where('coin_id', $coinId);
                    if ($platformIds !== []) {
                        $deleteQuery->whereNotIn('platform_id', $platformIds);
                    }
                    $deleteQuery->delete();
                }
            });

            $run->markSucceeded($processed, 'Synced coin network platforms from CoinGecko.');

            app(NetworkCatalogService::class)->forgetAvailableCache();

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }
}
