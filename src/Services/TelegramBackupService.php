<?php

namespace FieldTechVN\TelegramBackup\Services;

use FieldTechVN\TelegramBackup\Models\TelegramBot;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TelegramBackupService
{

    /**
     * Check if Spatie Laravel Backup is installed
     */
    protected function isSpatieBackupInstalled(): bool
    {
        return class_exists(\Spatie\Backup\Events\BackupWasSuccessful::class);
    }

    /**
     * Send backup success notification
     */
    public function notifyBackupSuccess($event): void
    {
        if (!$this->isSpatieBackupInstalled()) {
            Log::warning('Telegram Backup Service: Spatie Laravel Backup package is not installed.');
            return;
        }

        if (!config('telegram-backup-filamentphp.backup.notify_on_success', true)) {
            return;
        }

        // $this->sendNotification(
        //     config('telegram-backup-filamentphp.templates.backup_success', '✅ Backup completed successfully!'),
        //     $this->formatBackupDetails($event)
        // );
    }

    /**
     * Send backup failure notification
     */
    public function notifyBackupFailed($event): void
    {
        if (!$this->isSpatieBackupInstalled()) {
            Log::warning('Telegram Backup Service: Spatie Laravel Backup package is not installed.');
            return;
        }

        if (!config('telegram-backup-filamentphp.backup.notify_on_failure', true)) {
            return;
        }

        $this->sendNotification(
            config('telegram-backup-filamentphp.templates.backup_failed', '❌ Backup failed!'),
            $this->formatBackupError($event)
        );
    }

    /**
     * Send cleanup success notification
     */
    public function notifyCleanupSuccess($event): void
    {
        if (!$this->isSpatieBackupInstalled()) {
            return;
        }

        $this->sendNotification(
            '🧹 Cleanup completed successfully!',
            "Backup cleanup has been completed successfully."
        );
    }

    /**
     * Send cleanup failure notification
     */
    public function notifyCleanupFailed($event): void
    {
        if (!$this->isSpatieBackupInstalled()) {
            return;
        }

        $errorMessage = property_exists($event, 'exception') 
            ? $event->exception->getMessage() 
            : 'Unknown error';

        $this->sendNotification(
            '❌ Cleanup failed!',
            "Backup cleanup has failed: " . $errorMessage
        );
    }

    /**
     * Send healthy backup notification
     */
    public function notifyHealthyBackup($event): void
    {
        if (!$this->isSpatieBackupInstalled()) {
            return;
        }

        try {
            $backupDestination = $event->backupDestination ?? null;
            $backupName = $backupDestination ? $backupDestination->backupName() : 'Unknown';
        } catch (\Exception $e) {
            $backupName = 'Unknown';
        }
        
        $this->sendNotification(
            '✅ Healthy backup found!',
            "A healthy backup was found for: " . $backupName
        );
    }

    /**
     * Send unhealthy backup notification
     */
    public function notifyUnhealthyBackup($event): void
    {
        if (!$this->isSpatieBackupInstalled()) {
            return;
        }

        try {
            $backupDestination = $event->backupDestination ?? null;
            $backupName = $backupDestination ? $backupDestination->backupName() : 'Unknown';
        } catch (\Exception $e) {
            $backupName = 'Unknown';
        }
        
        $this->sendNotification(
            '⚠️ Unhealthy backup found!',
            "An unhealthy backup was found for: " . $backupName
        );
    }

    /**
     * Send notification to active bots and their associated chats
     */
    protected function sendNotification(string $title, string $message): void
    {
        try {
            $bots = TelegramBot::where('is_active', true)->get();

            foreach ($bots as $bot) {
                // Get active chats associated with this bot (many-to-many)
                $chats = $bot->chats()->where('is_active', true)->get();

                if ($chats->isEmpty()) {
                    // Fallback to config chat_id if no chats configured
                    $chatId = config('telegram-backup-filamentphp.backup.chat_id') ?? env('BACKUP_TELEGRAM_CHAT_ID');
                    
                    if (empty($chatId)) {
                        Log::warning("No active chats configured for bot {$bot->bot_username} and no chat ID in config.");
                        continue;
                    }

                    $fullMessage = "<b>{$title}</b>\n\n{$message}";
                    $response = Http::timeout(config('telegram-backup-filamentphp.api.timeout', 30))
                        ->post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => $fullMessage,
                            'parse_mode' => 'HTML',
                        ]);

                    if (!$response->successful()) {
                        Log::error('Failed to send Telegram backup notification: ' . $response->body());
                    }
                } else {
                    // Send to all active chats for the bot
                    foreach ($chats as $chat) {
                        $fullMessage = "<b>{$title}</b>\n\n{$message}";
                        
                        $response = Http::timeout(config('telegram-backup-filamentphp.api.timeout', 30))
                            ->post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
                                'chat_id' => $chat->chat_id,
                                'text' => $fullMessage,
                                'parse_mode' => 'HTML',
                            ]);

                        if (!$response->successful()) {
                            Log::error('Failed to send Telegram backup notification: ' . $response->body());
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram backup notification: ' . $e->getMessage());
        }
    }

    /**
     * Format backup details for notification
     */
    protected function formatBackupDetails($event): string
    {
        $details = "Backup completed successfully!\n";
        
        if (property_exists($event, 'backupDestination')) {
            $backupDestination = $event->backupDestination;
            $details .= "Backup Name: " . $backupDestination->backupName() . "\n";
            $details .= "Disk: " . $backupDestination->diskName() . "\n";
            
            $newestBackup = $backupDestination->newestBackup();
            if ($newestBackup && $newestBackup->exists()) {
                $details .= "Path: " . $newestBackup->path() . "\n";
                $details .= "Size: " . $this->formatBytes($newestBackup->sizeInBytes()) . "\n";
            }
        }

        return $details;
    }

    /**
     * Format backup error for notification
     */
    protected function formatBackupError($event): string
    {
        $error = "Backup failed!\n";
        
        if (property_exists($event, 'backupDestination')) {
            $error .= "Backup Name: " . $event->backupDestination->backupName() . "\n";
        }
        
        if (property_exists($event, 'exception')) {
            $error .= "Error: " . $event->exception->getMessage() . "\n";
        }

        return $error;
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
