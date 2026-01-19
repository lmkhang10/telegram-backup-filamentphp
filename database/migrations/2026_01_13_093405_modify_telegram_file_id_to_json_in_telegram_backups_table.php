<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_backups', function (Blueprint $table) {
            // Change telegram_file_id and telegram_message_id from string/integer to JSON
            $table->json('telegram_file_id')->nullable()->change();
            $table->json('telegram_message_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_backups', function (Blueprint $table) {
            // Convert back to string/integer (will store JSON as string)
            $table->text('telegram_file_id')->nullable()->change();
            $table->bigInteger('telegram_message_id')->nullable()->change();
        });
    }
};
