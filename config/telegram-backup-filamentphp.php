<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot API Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for Telegram Bot API interactions
    |
    */

    'api' => [
        'base_url' => 'https://api.telegram.org/bot',
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Bot Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration for new bots
    |
    */

    'default_bot' => [
        'is_active' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Default webhook settings
    |
    */

    'webhook' => [
        'default_events' => [
            'message',
            'edited_message',
            'channel_post',
            'edited_channel_post',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    |
    | Templates for different types of messages
    |
    */

    'templates' => [
        'backup_success' => '✅ Backup completed successfully!',
        'backup_failed' => '❌ Backup failed!',
        'test_message' => '🧪 Test message from Telegram Backup',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Integration
    |--------------------------------------------------------------------------
    |
    | Settings for integration with Spatie Laravel Backup
    | Cloned from raziul/laravel-backup-telegram package
    |
    */

    'backup' => [
        'enabled' => env('TELEGRAM_BACKUP_ENABLED', false),
        'notify_on_success' => true,
        'notify_on_failure' => true,

        // Database has highest priority - bots and chats must be configured via Filament admin panel
        'token' => \FieldTechVN\TelegramBackup\Helpers\ConfigHelper::getDefaultBotToken(),
        'chat_id' => \FieldTechVN\TelegramBackup\Helpers\ConfigHelper::getDefaultChatId(),
        'chunk_size' => env('BACKUP_TELEGRAM_CHUNK_SIZE', 1), // in megabytes (max 49 MB)
    ],
];
