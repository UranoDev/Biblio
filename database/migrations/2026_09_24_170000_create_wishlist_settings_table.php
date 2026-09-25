<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The URL of the public Amazon wishlist to sync, and when it was last
        // synced. A single row: changing the URL replaces the one there is.
        Schema::create('wishlist_settings', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_settings');
    }
};
