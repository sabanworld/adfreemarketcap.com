<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DexChartSeries;
use App\Models\DexPair;
use App\Models\DexToken;
use App\Services\MarketData\DexChartService;
use App\Services\MarketData\DexNetwork;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DexPairSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['WETH/USDC', 'WETH', 'USDC', 'Uniswap v3', 'Ethereum', 'verified', 3245.12, 1.82, 48_200_000, 312_000_000, 18420, 90, true, 1],
            ['SOL/USDC', 'SOL', 'USDC', 'Orca', 'Solana', 'verified', 168.4, -2.41, 22_100_000, 98_400_000, 42100, 120, true, 2],
            ['PEPE/WETH', 'PEPE', 'WETH', 'Uniswap v2', 'Ethereum', 'partial', 0.00000112, 18.4, 4_800_000, 22_100_000, 9100, 2, true, 3],
            ['BRETT/WETH', 'BRETT', 'WETH', 'Aerodrome', 'Base', 'unverified', 0.0841, 42.2, 890_000, 6_200_000, 5400, 0.4, true, 4],
            ['CAKE/BNB', 'CAKE', 'BNB', 'PancakeSwap', 'BNB Chain', 'verified', 2.14, 3.1, 6_400_000, 18_900_000, 7200, 400, false, 5],
            ['ARB/USDC', 'ARB', 'USDC', 'Camelot', 'Ethereum', 'verified', 0.72, -1.1, 3_200_000, 9_400_000, 3100, 30, false, 6],
            ['WIF/SOL', 'WIF', 'SOL', 'Raydium', 'Solana', 'partial', 1.92, 9.7, 2_100_000, 14_200_000, 8800, 5, true, 7],
            ['DEGEN/WETH', 'DEGEN', 'WETH', 'Uniswap v3', 'Base', 'unverified', 0.0088, -12.4, 420_000, 3_100_000, 2600, 1.2, false, 8],
        ];

        $charts = app(DexChartService::class);

        foreach ($rows as [$pair, $base, $quote, $dex, $chain, $audit, $price, $change, $liq, $vol, $txns, $daysAgo, $trending, $rank]) {
            $slug = DexPair::makeSlug($pair, $chain, $dex);
            $networkId = DexNetwork::idFromChainLabel($chain) ?? Str::slug((string) $chain);
            $baseAddress = '0x' . substr(md5($pair . $chain . 'base'), 0, 40);

            $token = DexToken::query()->updateOrCreate(
                [
                    'network_id' => $networkId,
                    'address' => $baseAddress,
                ],
                [
                    'symbol' => $base,
                    'name' => $base,
                    'price' => $price,
                    'percent_change_24h' => $change,
                    'liquidity_usd' => $liq,
                    'volume_24h' => $vol,
                    'detail_synced_at' => now(),
                    'trades_synced_at' => now(),
                    'synced_at' => now(),
                ],
            );

            // Upsert by slug so older local demo rows (provider geckoterminal, null external_id)
            // are converted into proper seed pairs instead of sitting beside a duplicate.
            $model = DexPair::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'provider' => 'seed',
                    'external_id' => 'seed_' . $slug,
                    'pair' => $pair,
                    'base_symbol' => $base,
                    'quote_symbol' => $quote,
                    'dex' => $dex,
                    'chain' => $chain,
                    'network_id' => $networkId,
                    'contract_address' => '0x' . substr(md5($pair . $chain), 0, 40),
                    'base_token_address' => $baseAddress,
                    'dex_token_id' => $token->id,
                    'audit_status' => $audit,
                    'price' => $price,
                    'percent_change_24h' => $change,
                    'liquidity_usd' => $liq,
                    'volume_24h' => $vol,
                    'txns_24h' => $txns,
                    'paired_at' => now()->subDays((int) $daysAgo)->subHours(random_int(0, 12)),
                    'is_trending' => $trending,
                    'rank' => $rank,
                    'synced_at' => now(),
                    'detail_synced_at' => now(),
                    'trades_synced_at' => now(),
                ],
            );

            $charts->upsertSeriesFor($model, $this->demoSeries((float) $price, (float) $change));
            $charts->upsertSeriesFor($token, $this->demoSeries((float) $price, (float) $change));
        }
    }

    /**
     * @return array<string, list<array{0: int, 1: float}>>
     */
    private function demoSeries(float $price, float $change24h): array
    {
        $end = now()->getTimestampMs();
        $intraday = [];
        $short = [];
        $daily = [];

        $startIntraday = $price / max(0.01, 1 + ($change24h / 100));
        for ($i = 0; $i < 288; $i++) {
            $t = $end - ((287 - $i) * 5 * 60 * 1000);
            $progress = $i / 287;
            $intraday[] = [$t, $startIntraday + (($price - $startIntraday) * $progress)];
        }

        for ($i = 0; $i < 168; $i++) {
            $t = $end - ((167 - $i) * 3600 * 1000);
            $wave = sin($i / 8) * ($price * 0.02);
            $short[] = [$t, max(0.00000001, $price + $wave)];
        }

        for ($i = 0; $i < 365; $i++) {
            $t = $end - ((364 - $i) * 86400 * 1000);
            $wave = sin($i / 20) * ($price * 0.08);
            $daily[] = [$t, max(0.00000001, $price + $wave)];
        }

        return [
            DexChartSeries::SERIES_INTRADAY => $intraday,
            DexChartSeries::SERIES_SHORT => $short,
            DexChartSeries::SERIES_DAILY => $daily,
        ];
    }
}
