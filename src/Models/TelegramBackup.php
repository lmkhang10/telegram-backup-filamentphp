<?php

namespace FieldTechVN\TelegramBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramBackup extends Model
{
    protected $table = 'telegram_backups';

    protected $fillable = [
        'bot_id',
        'backup_name',
        'backup_path',
        'telegram_file_id',
        'telegram_message_id',
        'telegram_chat_id',
        'file_size',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'file_size' => 'integer',
        'telegram_message_id' => 'array',
        'telegram_file_id' => 'array',
    ];

    /**
     * Get the bot that sent this backup.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, 'bot_id');
    }
}
