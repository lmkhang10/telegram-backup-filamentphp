<?php

namespace FieldTechVN\TelegramBackup\Helpers;

use Illuminate\Support\Facades\Cache;
use FieldTechVN\TelegramBackup\Models\TelegramBot;
use FieldTechVN\TelegramBackup\Models\TelegramChat;

class ConfigHelper
{
    /**
     * Cache key for default bot token
     */
    private const CACHE_KEY_BOT_TOKEN = 'telegram_backup.default_bot_token';

    /**
     * Cache key for default chat ID
     */
    private const CACHE_KEY_CHAT_ID = 'telegram_backup.default_chat_id';

    /**
     * Cache TTL in seconds (24 hours)
     */
    private const CACHE_TTL = 86400;

    /**
     * Get default bot token from database if env is not set
     */
    public static function getDefaultBotToken(): ?string
    {
        // During config loading, services might not be available
        // Return null and let the service handle database lookup when needed
        if (!app()->bound('cache') || !app()->isBooted()) {
            return null;
        }

        try {
            return Cache::remember(self::CACHE_KEY_BOT_TOKEN, self::CACHE_TTL, function () {
                try {
                    $bot = TelegramBot::where('is_active', true)->first();
                    return $bot?->bot_token;
                } catch (\Exception $e) {
                    // Database might not be ready yet
                    return null;
                }
            });
        } catch (\Exception $e) {
            // If cache fails, try direct database query
            try {
                $bot = TelegramBot::where('is_active', true)->first();
                return $bot?->bot_token;
            } catch (\Exception $e) {
                return null;
            }
        }
    }

    /**
     * Get default chat ID from database if env is not set
     */
    public static function getDefaultChatId(): ?string
    {
        // During config loading, services might not be available
        // Return null and let the service handle database lookup when needed
        if (!app()->bound('cache') || !app()->isBooted()) {
            return null;
        }

        try {
            return Cache::remember(self::CACHE_KEY_CHAT_ID, self::CACHE_TTL, function () {
                try {
                    $bot = TelegramBot::where('is_active', true)->first();
                    if (!$bot) {
                        return null;
                    }
                    
                    // Get first active chat from many-to-many relationship
                    $chat = $bot->chats()->where('is_active', true)->first();
                    
                    return $chat?->chat_id;
                } catch (\Exception $e) {
                    // Database might not be ready yet
                    return null;
                }
            });
        } catch (\Exception $e) {
            // If cache fails, try direct database query
            try {
                $bot = TelegramBot::where('is_active', true)->first();
                if (!$bot) {
                    return null;
                }
                
                // Get first active chat from many-to-many relationship
                $chat = $bot->chats()->where('is_active', true)->first();
                
                return $chat?->chat_id;
            } catch (\Exception $e) {
                return null;
            }
        }
    }

    /**
     * Clear all cached config values
     */
    public static function clearCache(): void
    {
        // Only clear cache if app is booted and Cache is available
        if (app()->bound('cache') && app()->isBooted()) {
            Cache::forget(self::CACHE_KEY_BOT_TOKEN);
            Cache::forget(self::CACHE_KEY_CHAT_ID);
        }
    }
}
