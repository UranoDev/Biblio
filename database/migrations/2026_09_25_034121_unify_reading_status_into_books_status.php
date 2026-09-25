<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Move reading state into the status column before dropping the flags.
        DB::statement("UPDATE books SET status = 'reading' WHERE is_reading = 1 AND status = 'owned'");
        DB::statement("UPDATE books SET status = 'read' WHERE is_read = 1 AND is_reading = 0 AND status = 'owned'");

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['is_reading', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('is_read')->default(false)->after('read_on');
            $table->boolean('is_reading')->default(false)->after('is_read');
        });

        DB::statement("UPDATE books SET is_reading = 1 WHERE status = 'reading'");
        DB::statement("UPDATE books SET status = 'owned' WHERE status = 'reading'");
        DB::statement("UPDATE books SET is_read = 1 WHERE status = 'read'");
        DB::statement("UPDATE books SET status = 'owned' WHERE status = 'read'");
    }
};
