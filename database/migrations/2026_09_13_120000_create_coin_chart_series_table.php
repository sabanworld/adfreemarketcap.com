<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_chart_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->string('series', 16);
            $table->json('points')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['coin_id', 'series']);
            $table->index(['series', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_chart_series');
    }
};
