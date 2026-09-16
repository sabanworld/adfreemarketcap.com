<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Coin;
use App\Models\CoinPlatform;
use App\Services\MarketData\DexAuditStatusResolver;
use App\Services\MarketData\DTOs\DexPairData;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DexAuditStatusResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_markets_platform_match_is_verified(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'pepe',
            'symbol' => 'PEPE',
            'name' => 'Pepe',
            'rank' => 50,
        ]);

        CoinPlatform::query()->create([
            'coin_id' => $coin->id,
            'platform_id' => 'ethereum',
            'contract_address' => '0xPePeBaseTokenAddress000000000000000001',
        ]);

        $status = app(DexAuditStatusResolver::class)->resolve(
            'eth',
            '0xpepebasetokenaddress000000000000000001',
            'pepe',
        );

        $this->assertSame(DexAuditStatusResolver::STATUS_VERIFIED, $status);
    }

    public function test_coingecko_id_alone_is_partial(): void
    {
        $status = app(DexAuditStatusResolver::class)->resolve(
            'eth',
            '0xunknown000000000000000000000000000001',
            'some-meme',
        );

        $this->assertSame(DexAuditStatusResolver::STATUS_PARTIAL, $status);
    }

    public function test_neither_signal_is_unverified(): void
    {
        $status = app(DexAuditStatusResolver::class)->resolve(
            'eth',
            '0xunknown000000000000000000000000000001',
            null,
        );

        $this->assertSame(DexAuditStatusResolver::STATUS_UNVERIFIED, $status);
    }

    public function test_unranked_markets_coin_does_not_verify(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'ghost',
            'symbol' => 'GHOST',
            'name' => 'Ghost',
            'rank' => null,
        ]);

        CoinPlatform::query()->create([
            'coin_id' => $coin->id,
            'platform_id' => 'ethereum',
            'contract_address' => '0xghost00000000000000000000000000000001',
        ]);

        $status = app(DexAuditStatusResolver::class)->resolve(
            'eth',
            '0xghost00000000000000000000000000000001',
            'ghost',
        );

        $this->assertSame(DexAuditStatusResolver::STATUS_PARTIAL, $status);
    }

    public function test_solana_address_match_is_case_sensitive(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'bonk',
            'symbol' => 'BONK',
            'name' => 'Bonk',
            'rank' => 60,
        ]);

        CoinPlatform::query()->create([
            'coin_id' => $coin->id,
            'platform_id' => 'solana',
            'contract_address' => 'BonkBaseTokenAddress1111111111111111111',
        ]);

        $resolver = app(DexAuditStatusResolver::class);

        $this->assertSame(
            DexAuditStatusResolver::STATUS_VERIFIED,
            $resolver->resolve('solana', 'BonkBaseTokenAddress1111111111111111111', null),
        );

        $this->assertSame(
            DexAuditStatusResolver::STATUS_UNVERIFIED,
            $resolver->resolve('solana', 'bonkbasetokenaddress1111111111111111111', null),
        );
    }

    public function test_resolve_from_pair_data_uses_dto_fields(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'weth',
            'symbol' => 'WETH',
            'name' => 'WETH',
            'rank' => 2,
        ]);

        CoinPlatform::query()->create([
            'coin_id' => $coin->id,
            'platform_id' => 'ethereum',
            'contract_address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
        ]);

        $data = new DexPairData(
            externalId: 'eth_pool',
            pair: 'WETH/USDC',
            baseSymbol: 'WETH',
            quoteSymbol: 'USDC',
            dex: 'Uniswap V3',
            chain: 'Ethereum',
            networkId: 'eth',
            contractAddress: '0xpool',
            baseTokenAddress: '0xC02aaa39b223FE8D0A0e5C4F27eAD9083C756Cc2',
            quoteTokenAddress: null,
            baseTokenName: 'WETH',
            coingeckoCoinId: 'weth',
            auditStatus: 'unverified',
            price: 1.0,
            percentChange24h: 0.0,
            liquidityUsd: 1.0,
            volume24h: 1.0,
            volume1h: null,
            volume6h: null,
            fdvUsd: null,
            marketCapUsd: null,
            txns24h: null,
            buys24h: null,
            sells24h: null,
            pairedAt: null,
            isTrending: false,
            rank: null,
        );

        $this->assertSame(
            DexAuditStatusResolver::STATUS_VERIFIED,
            app(DexAuditStatusResolver::class)->resolveFromPairData($data),
        );
    }

    public function test_warm_listed_lookup_avoids_per_pair_queries(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'pepe',
            'symbol' => 'PEPE',
            'name' => 'Pepe',
            'rank' => 50,
        ]);

        CoinPlatform::query()->create([
            'coin_id' => $coin->id,
            'platform_id' => 'ethereum',
            'contract_address' => '0xPePeBaseTokenAddress000000000000000001',
        ]);

        $pairs = collect([
            new DexPairData(
                externalId: 'eth_a',
                pair: 'PEPE/WETH',
                baseSymbol: 'PEPE',
                quoteSymbol: 'WETH',
                dex: 'Uniswap V3',
                chain: 'Ethereum',
                networkId: 'eth',
                contractAddress: '0xpool',
                baseTokenAddress: '0xpepebasetokenaddress000000000000000001',
                quoteTokenAddress: null,
                baseTokenName: 'Pepe',
                coingeckoCoinId: 'pepe',
                auditStatus: 'unverified',
                price: 1.0,
                percentChange24h: 0.0,
                liquidityUsd: 1.0,
                volume24h: 1.0,
                volume1h: null,
                volume6h: null,
                fdvUsd: null,
                marketCapUsd: null,
                txns24h: null,
                buys24h: null,
                sells24h: null,
                pairedAt: null,
                isTrending: false,
                rank: null,
            ),
            new DexPairData(
                externalId: 'eth_b',
                pair: 'UNKNOWN/WETH',
                baseSymbol: 'UNKNOWN',
                quoteSymbol: 'WETH',
                dex: 'Uniswap V3',
                chain: 'Ethereum',
                networkId: 'eth',
                contractAddress: '0xpool2',
                baseTokenAddress: '0xunknown000000000000000000000000000001',
                quoteTokenAddress: null,
                baseTokenName: 'Unknown',
                coingeckoCoinId: null,
                auditStatus: 'unverified',
                price: 1.0,
                percentChange24h: 0.0,
                liquidityUsd: 1.0,
                volume24h: 1.0,
                volume1h: null,
                volume6h: null,
                fdvUsd: null,
                marketCapUsd: null,
                txns24h: null,
                buys24h: null,
                sells24h: null,
                pairedAt: null,
                isTrending: false,
                rank: null,
            ),
        ]);

        $resolver = app(DexAuditStatusResolver::class);
        $resolver->warmListedLookup($pairs);

        $selects = 0;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$selects): void {
            if (str_starts_with(strtolower($query->sql), 'select')) {
                $selects++;
            }
        });

        $this->assertSame(
            DexAuditStatusResolver::STATUS_VERIFIED,
            $resolver->resolveFromPairData($pairs[0]),
        );
        $this->assertSame(
            DexAuditStatusResolver::STATUS_UNVERIFIED,
            $resolver->resolveFromPairData($pairs[1]),
        );
        $this->assertSame(0, $selects);

        $resolver->clearListedLookup();
    }
}
