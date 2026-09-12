<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coins', function (Blueprint $table): void {
            $table->timestamp('tickers_synced_at')->nullable()->after('detail_synced_at');
        });

        Schema::create('coin_tickers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('coingecko');
            $table->string('exchange_id');
            $table->string('exchange_name');
            $table->string('base_symbol', 64);
            $table->string('target_symbol', 64);
            $table->string('pair');
            $table->decimal('price_usd', 36, 18)->nullable();
            $table->decimal('last_price', 36, 18)->nullable();
            $table->decimal('volume_24h_usd', 24, 2)->nullable();
            $table->decimal('volume_share_percent', 8, 4)->nullable();
            $table->decimal('bid_ask_spread_percent', 12, 6)->nullable();
            $table->string('trust_score', 16)->nullable();
            $table->boolean('is_anomaly')->default(false);
            $table->boolean('is_stale')->default(false);
            $table->string('trade_url', 2048)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('last_traded_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['coin_id', 'provider', 'exchange_id', 'base_symbol', 'target_symbol'],
                'coin_tickers_unique_market',
            );
            $table->index(['coin_id', 'volume_24h_usd']);
            $table->index(['coin_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_tickers');

        Schema::table('coins', function (Blueprint $table): void {
            $table->dropColumn('tickers_synced_at');
        });
    }
};
