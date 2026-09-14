<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nostr_notes', function (Blueprint $table) {
            $table->id();
            $table->string('coin_slug', 64)->index();
            $table->string('event_id', 64)->unique();
            $table->string('pubkey', 64)->index();
            $table->string('author_name', 128)->nullable();
            $table->string('author_npub', 128)->nullable();
            $table->text('content');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nostr_notes');
    }
};
