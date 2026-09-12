<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_treasury_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_holdings', 24, 8);
            $table->decimal('total_value_usd', 24, 2)->nullable();
            $table->decimal('market_cap_dominance', 8, 4)->nullable();
            $table->unsignedInteger('companies_count')->default(0);
            $table->string('provider')->default('coingecko');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique('coin_id');
        });

        Schema::create('coin_treasury_holders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->string('country', 8)->nullable();
            $table->decimal('total_holdings', 24, 8);
            $table->decimal('total_entry_value_usd', 24, 2)->nullable();
            $table->decimal('total_current_value_usd', 24, 2)->nullable();
            $table->decimal('percentage_of_total_supply', 12, 6)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();

            $table->unique(['coin_id', 'name']);
            $table->index(['coin_id', 'rank']);
        });

        Schema::create('coin_market_cycle_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 24, 8)->nullable();
            $table->decimal('ma111', 24, 8)->nullable();
            $table->decimal('ma350x2', 24, 8)->nullable();
            $table->decimal('ma_gap_percent', 12, 4)->nullable();
            $table->string('pi_cycle_status', 32)->nullable();
            $table->timestamp('last_cross_at')->nullable();
            $table->timestamp('last_halving_at')->nullable();
            $table->timestamp('next_halving_at')->nullable();
            $table->unsignedInteger('days_since_halving')->nullable();
            $table->unsignedInteger('days_until_halving')->nullable();
            $table->decimal('cycle_progress_percent', 8, 4)->nullable();
            $table->unsignedTinyInteger('halving_epoch')->nullable();
            $table->json('chart_price')->nullable();
            $table->json('chart_ma111')->nullable();
            $table->json('chart_ma350x2')->nullable();
            $table->string('provider')->default('bitcoin_com_charts');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique('coin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_market_cycle_snapshots');
        Schema::dropIfExists('coin_treasury_holders');
        Schema::dropIfExists('coin_treasury_snapshots');
    }
};
