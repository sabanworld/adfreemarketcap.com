<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('price_alerts_enabled')->default(true);
            $table->date('watchlist_recap_sent_on')->nullable();
        });

        Schema::create('watchlist_price_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained()->cascadeOnDelete();
            $table->string('window', 8);
            $table->string('direction', 8)->nullable();
            $table->unsignedInteger('notified_percent')->default(0);
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'coin_id', 'window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_price_alerts');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['price_alerts_enabled', 'watchlist_recap_sent_on']);
        });
    }
};
