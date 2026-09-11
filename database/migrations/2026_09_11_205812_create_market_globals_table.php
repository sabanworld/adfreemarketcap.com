<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_globals', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_market_cap', 24, 2)->nullable();
            $table->decimal('total_volume_24h', 24, 2)->nullable();
            $table->decimal('btc_dominance', 8, 4)->nullable();
            $table->unsignedInteger('active_cryptocurrencies')->nullable();
            $table->string('provider', 32)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_globals');
    }
};
