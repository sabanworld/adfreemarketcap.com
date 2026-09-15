<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dex_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('network_id', 64);
            $table->string('address');
            $table->string('symbol');
            $table->string('name')->nullable();
            $table->string('coingecko_coin_id')->nullable();
            $table->decimal('price', 36, 18)->nullable();
            $table->decimal('percent_change_24h', 12, 4)->nullable();
            $table->decimal('fdv_usd', 24, 2)->nullable();
            $table->decimal('market_cap_usd', 24, 2)->nullable();
            $table->decimal('liquidity_usd', 24, 2)->nullable();
            $table->decimal('volume_24h', 24, 2)->nullable();
            $table->unsignedInteger('holders_count')->nullable();
            $table->timestamp('detail_synced_at')->nullable();
            $table->timestamp('trades_synced_at')->nullable();
            $table->timestamp('holders_synced_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['network_id', 'address']);
            $table->index('symbol');
        });

        Schema::table('dex_pairs', function (Blueprint $table): void {
            $table->string('network_id', 64)->nullable()->after('chain');
            $table->string('base_token_address')->nullable()->after('contract_address');
            $table->string('quote_token_address')->nullable()->after('base_token_address');
            $table->foreignId('dex_token_id')->nullable()->after('quote_token_address')
                ->constrained('dex_tokens')->nullOnDelete();
            $table->decimal('fdv_usd', 24, 2)->nullable()->after('volume_24h');
            $table->decimal('market_cap_usd', 24, 2)->nullable()->after('fdv_usd');
            $table->unsignedInteger('buys_24h')->nullable()->after('txns_24h');
            $table->unsignedInteger('sells_24h')->nullable()->after('buys_24h');
            $table->decimal('volume_1h', 24, 2)->nullable()->after('volume_24h');
            $table->decimal('volume_6h', 24, 2)->nullable()->after('volume_1h');
            $table->timestamp('detail_synced_at')->nullable()->after('synced_at');
            $table->timestamp('trades_synced_at')->nullable()->after('detail_synced_at');

            $table->index(['network_id', 'base_token_address']);
        });

        Schema::create('dex_chart_series', function (Blueprint $table): void {
            $table->id();
            $table->morphs('chartable');
            $table->string('series', 16);
            $table->json('points')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['chartable_type', 'chartable_id', 'series'], 'dex_chart_series_unique');
        });

        Schema::create('dex_trades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dex_pair_id')->nullable()->constrained('dex_pairs')->cascadeOnDelete();
            $table->foreignId('dex_token_id')->nullable()->constrained('dex_tokens')->cascadeOnDelete();
            $table->string('tx_hash')->nullable();
            $table->string('kind', 16)->nullable();
            $table->decimal('price_usd', 36, 18)->nullable();
            $table->decimal('volume_usd', 24, 2)->nullable();
            $table->string('from_token_amount', 64)->nullable();
            $table->string('to_token_amount', 64)->nullable();
            $table->string('trader_address')->nullable();
            $table->timestamp('traded_at')->nullable();
            $table->timestamps();

            $table->index(['dex_pair_id', 'traded_at']);
            $table->index(['dex_token_id', 'traded_at']);
        });

        Schema::create('dex_token_holders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dex_token_id')->constrained('dex_tokens')->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->string('address');
            $table->string('label')->nullable();
            $table->string('amount', 64)->nullable();
            $table->decimal('percentage', 12, 6)->nullable();
            $table->decimal('value_usd', 24, 2)->nullable();
            $table->string('explorer_url')->nullable();
            $table->timestamps();

            $table->unique(['dex_token_id', 'rank']);
            $table->index(['dex_token_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dex_token_holders');
        Schema::dropIfExists('dex_trades');
        Schema::dropIfExists('dex_chart_series');

        Schema::table('dex_pairs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dex_token_id');
            $table->dropColumn([
                'network_id',
                'base_token_address',
                'quote_token_address',
                'fdv_usd',
                'market_cap_usd',
                'buys_24h',
                'sells_24h',
                'volume_1h',
                'volume_6h',
                'detail_synced_at',
                'trades_synced_at',
            ]);
        });

        Schema::dropIfExists('dex_tokens');
    }
};
