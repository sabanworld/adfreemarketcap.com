<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dex_pairs', function (Blueprint $table): void {
            $table->string('provider')->default('geckoterminal')->after('slug');
            $table->string('external_id')->nullable()->after('provider');
            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dex_pairs', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'external_id']);
            $table->dropColumn(['provider', 'external_id']);
        });
    }
};
