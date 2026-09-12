<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dex_pairs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('pair');
            $table->string('base_symbol');
            $table->string('quote_symbol');
            $table->string('dex');
            $table->string('chain');
            $table->string('contract_address')->nullable();
            $table->string('audit_status')->default('unverified');
            $table->decimal('price', 36, 18)->nullable();
            $table->decimal('percent_change_24h', 12, 4)->nullable();
            $table->decimal('liquidity_usd', 24, 2)->nullable();
            $table->decimal('volume_24h', 24, 2)->nullable();
            $table->unsignedInteger('txns_24h')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->boolean('is_trending')->default(false);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['chain', 'liquidity_usd']);
            $table->index(['is_trending', 'volume_24h']);
            $table->index('percent_change_24h');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dex_pairs');
    }
};
