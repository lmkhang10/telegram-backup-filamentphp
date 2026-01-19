<?php

namespace FieldTechVN\TelegramBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use FieldTechVN\TelegramBackup\Helpers\ConfigHelper;

class TelegramBot extends Model
{
    use SoftDeletes;

    protected $table = 'telegram_bots';

    protected $fillable = [
        'bot_token',
        'bot_username',
        'bot_name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Clear cache when bot is created, updated, or deleted
        static::created(function () {
            ConfigHelper::clearCache();
        });

        static::updated(function () {
            ConfigHelper::clearCache();
        });

        static::deleted(function () {
            ConfigHelper::clearCache();
        });

        static::restored(function () {
            ConfigHelper::clearCache();
        });
    }

    /**
     * Get the backups sent by this bot.
     */
    public function backups(): HasMany
    {
        return $this->hasMany(TelegramBackup::class, 'bot_id');
    }

    /**
     * Get the chats that can be used by this bot.
     */
    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(TelegramChat::class, 'telegram_bot_chat', 'bot_id', 'chat_id')
            ->withTimestamps();
    }
}
