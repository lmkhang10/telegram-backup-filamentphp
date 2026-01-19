<?php

namespace FieldTechVN\TelegramBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TelegramChat extends Model
{
    use SoftDeletes;

    protected $table = 'telegram_chats';

    protected $fillable = [
        'chat_id',
        'chat_type',
        'name',
        'username',
        'first_name',
        'last_name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the bots that can use this chat.
     */
    public function bots(): BelongsToMany
    {
        return $this->belongsToMany(TelegramBot::class, 'telegram_bot_chat', 'chat_id', 'bot_id')
            ->withTimestamps();
    }
}
