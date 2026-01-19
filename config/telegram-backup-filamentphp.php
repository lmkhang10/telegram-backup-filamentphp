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

        // Original package config (for backward compatibility)
        // Database has highest priority, falls back to env variables if no bot in database
        'token' => \FieldTechVN\TelegramBackup\Helpers\ConfigHelper::getDefaultBotToken() ?: env('BACKUP_TELEGRAM_BOT_TOKEN'),
        'chat_id' => \FieldTechVN\TelegramBackup\Helpers\ConfigHelper::getDefaultChatId() ?: env('BACKUP_TELEGRAM_CHAT_ID'),
        'chunk_size' => env('BACKUP_TELEGRAM_CHUNK_SIZE', 1), // in megabytes (max 49 MB)
    ],
];
