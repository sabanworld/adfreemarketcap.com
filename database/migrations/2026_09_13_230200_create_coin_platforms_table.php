<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->string('platform_id', 64);
            $table->string('contract_address', 128)->nullable();
            $table->timestamps();

            $table->unique(['coin_id', 'platform_id']);
            $table->index('platform_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_platforms');
    }
};
