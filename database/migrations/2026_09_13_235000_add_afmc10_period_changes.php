<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coins', function (Blueprint $table) {
            $table->decimal('percent_change_30d', 12, 4)->nullable()->after('percent_change_90d');
            $table->decimal('percent_change_200d', 12, 4)->nullable()->after('percent_change_30d');
            $table->decimal('percent_change_1y', 12, 4)->nullable()->after('percent_change_200d');
        });

        Schema::table('market_status_snapshots', function (Blueprint $table) {
            $table->decimal('afmc10_change_7d', 12, 4)->nullable()->after('afmc10_change_24h');
            $table->decimal('afmc10_change_30d', 12, 4)->nullable()->after('afmc10_change_7d');
            $table->decimal('afmc10_change_200d', 12, 4)->nullable()->after('afmc10_change_30d');
            $table->decimal('afmc10_change_1y', 12, 4)->nullable()->after('afmc10_change_200d');
        });
    }

    public function down(): void
    {
        Schema::table('coins', function (Blueprint $table) {
            $table->dropColumn(['percent_change_30d', 'percent_change_200d', 'percent_change_1y']);
        });

        Schema::table('market_status_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'afmc10_change_7d',
                'afmc10_change_30d',
                'afmc10_change_200d',
                'afmc10_change_1y',
            ]);
        });
    }
};
