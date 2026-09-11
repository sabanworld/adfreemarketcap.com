<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_provider_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->unique(['coin_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_provider_ids');
    }
};
