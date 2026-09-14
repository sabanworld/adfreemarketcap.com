<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_status_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('fear_greed_value')->nullable();
            $table->string('fear_greed_classification', 32)->nullable();
            $table->decimal('afmc10_value', 16, 4)->nullable();
            $table->decimal('afmc10_change_24h', 12, 4)->nullable();
            $table->decimal('afmc10_base_sum', 24, 2)->nullable();
            $table->decimal('altcoin_season_index', 8, 2)->nullable();
            $table->unsignedSmallInteger('altcoin_season_sample_size')->nullable();
            $table->string('provider', 64)->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_status_snapshots');
    }
};
