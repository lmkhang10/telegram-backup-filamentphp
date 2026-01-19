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
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('chat_id')->unique();
            $table->string('chat_type')->default('private'); // private, group, supergroup, channel
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('chat_id');
            $table->index('is_active');
        });

        // Create pivot table for many-to-many relationship
        Schema::create('telegram_bot_chat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('telegram_bots')->onDelete('cascade');
            $table->foreignId('chat_id')->constrained('telegram_chats')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['bot_id', 'chat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_chat');
        Schema::dropIfExists('telegram_chats');
    }
};
