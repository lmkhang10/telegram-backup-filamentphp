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
        Schema::create('telegram_backups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('bot_id')->constrained('telegram_bots')->onDelete('cascade');
            $table->string('backup_name');
            $table->string('backup_path')->nullable();
            $table->string('telegram_file_id')->nullable();
            $table->string('telegram_message_id')->nullable();
            $table->string('telegram_chat_id');
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('bot_id');
            $table->index('status');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_backups');
    }
};
