<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_globals', function (Blueprint $table) {
            $table->decimal('market_cap_change_percentage_24h', 12, 4)->nullable()->after('btc_dominance');
        });

        Schema::table('coins', function (Blueprint $table) {
            $table->decimal('percent_change_90d', 12, 4)->nullable()->after('percent_change_7d');
        });
    }

    public function down(): void
    {
        Schema::table('market_globals', function (Blueprint $table) {
            $table->dropColumn('market_cap_change_percentage_24h');
        });

        Schema::table('coins', function (Blueprint $table) {
            $table->dropColumn('percent_change_90d');
        });
    }
};
