<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('symbol', 32)->index();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->unsignedInteger('rank')->nullable()->index();
            $table->decimal('price', 24, 10)->nullable();
            $table->decimal('percent_change_1h', 12, 4)->nullable();
            $table->decimal('percent_change_24h', 12, 4)->nullable();
            $table->decimal('percent_change_7d', 12, 4)->nullable();
            $table->decimal('market_cap', 24, 2)->nullable();
            $table->decimal('volume_24h', 24, 2)->nullable();
            $table->decimal('circulating_supply', 28, 4)->nullable();
            $table->json('sparkline_7d')->nullable();
            $table->json('chart_7d')->nullable();
            $table->text('description')->nullable();
            $table->string('last_provider', 32)->nullable();
            $table->timestamp('market_synced_at')->nullable();
            $table->timestamp('detail_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coins');
    }
};
