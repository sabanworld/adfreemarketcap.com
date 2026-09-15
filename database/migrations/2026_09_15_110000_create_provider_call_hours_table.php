<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_call_hours', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->dateTime('hour_starts_at');
            $table->unsignedInteger('calls')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'hour_starts_at']);
            $table->index('hour_starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_call_hours');
    }
};
