<?php

declare(strict_types=1);

namespace FieldTechVN\TelegramBackup\Services;

use FieldTechVN\TelegramBackup\Models\TelegramBackup;
use Illuminate\Support\Facades\Http;

final class SendBackupFile
{
    public function handle($event): void
    {
        if (! class_exists(\Spatie\Backup\Events\BackupWasSuccessful::class)) {
            if (function_exists('consoleOutput')) {
                consoleOutput()->error('Spatie Laravel Backup package is not installed. Cannot send backup file to Telegram.');
            }
            \Illuminate\Support\Facades\Log::warning('Telegram Backup: Spatie Laravel Backup package is not installed. Cannot send backup file to Telegram.');

            return;
        }

        $backup = $event->backupDestination->newestBackup();

        if (! $backup->exists()) {
            consoleOutput()->error('Backup file does not exist.');

            return;
        }

        $chunkSize = min(config('telegram-backup-filamentphp.backup.chunk_size', 49), 49);

        // Get the actual file path - handle different disk types
        $path = null;

        try {
            $disk = $backup->disk();
            $backupPath = $backup->path();

            // Try different methods to get the file path
            if (method_exists($disk, 'path')) {
                $path = $disk->path($backupPath);
            } elseif (method_exists($disk, 'get')) {
                // For remote disks, we might need to download first
                $path = storage_path('app/temp/' . basename($backupPath));
                \Illuminate\Support\Facades\Storage::makeDirectory('temp');
                file_put_contents($path, $disk->get($backupPath));
            } else {
                // Fallback: try storage path
                $path = storage_path('app/' . $backupPath);
            }

            // Verify file exists
            if (! file_exists($path)) {
                consoleOutput()->error("Backup file does not exist at path: {$path}");
                \Illuminate\Support\Facades\Log::error("Backup file not found: {$path}. Backup path: {$backupPath}");

                return;
            }

            $fileSize = filesize($path);
            if ($fileSize === false || $fileSize === 0) {
                consoleOutput()->error("Backup file is empty or unreadable: {$path}");
                \Illuminate\Support\Facades\Log::error("Backup file is empty: {$path}");

                return;
            }
        } catch (\Exception $e) {
            consoleOutput()->error('Error getting backup file path: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Error getting backup file: ' . $e->getMessage(), [
                'backup_path' => $backup->path(),
                'exception' => $e,
            ]);

            return;
        }

        $backupName = basename($backup->path());

        $response = $backup->sizeInBytes() > $chunkSize * 1024 * 1024
            ? $this->splitAndSendFile($path, $chunkSize, $backupName, $fileSize)
            : $this->sendFile($path, $backupName, $fileSize);

        $response['ok'] ?? false
            ? consoleOutput()->comment('Backup sent to telegram.')
            : consoleOutput()->error('Failed to send backup file to Telegram.');
    }

    /**
     * Send file to telegram.
     */
    private function sendFile(string $filePath, string $backupName, int $fileSize): ?array
    {
        // Get all active bots from database
        $bots = \FieldTechVN\TelegramBackup\Models\TelegramBot::where('is_active', true)->get();

        if ($bots->isEmpty()) {
            // Fallback to config if no active bots in database
            $token = config('telegram-backup-filamentphp.backup.token') ?? config('backup-telegram.token');
            $chatId = config('telegram-backup-filamentphp.backup.chat_id') ?? config('backup-telegram.chat_id');

            if (empty($token) || empty($chatId)) {
                consoleOutput()->error('Telegram token or chat ID is not configured.');

                return null;
            }

            return $this->sendFileDirectly($token, $chatId, $filePath, $backupName, $fileSize, null);
        }

        // Send to all active bots and their active chats
        $allSuccess = true;
        $hasAnySuccess = false;

        foreach ($bots as $bot) {
            // Get active chats associated with this bot (many-to-many)
            $chats = $bot->chats()->where('is_active', true)->get();

            if ($chats->isEmpty()) {
                // Fallback to config chat_id if no chats configured for this bot
                $chatId = config('telegram-backup-filamentphp.backup.chat_id');

                if (! empty($chatId)) {
                    $result = $this->sendFileDirectly($bot->bot_token, $chatId, $filePath, $backupName, $fileSize, $bot->id);
                    if ($result['ok'] ?? false) {
                        $hasAnySuccess = true;
                    } else {
                        $allSuccess = false;
                    }
                }
            } else {
                // Send to all active chats for this bot
                foreach ($chats as $chat) {
                    $result = $this->sendFileDirectly($bot->bot_token, $chat->chat_id, $filePath, $backupName, $fileSize, $bot->id);
                    if ($result['ok'] ?? false) {
                        $hasAnySuccess = true;
                    } else {
                        $allSuccess = false;
                    }
                }
            }
        }

        return $hasAnySuccess ? ['ok' => true] : ['ok' => false];
    }

    /**
     * Send file directly to Telegram API
     */
    private function sendFileDirectly(string $token, string $chatId, string $filePath, string $backupName, int $fileSize, ?int $botId): ?array
    {
        try {
            // Verify file exists and is readable
            if (! file_exists($filePath)) {
                throw new \Exception("File does not exist: {$filePath}");
            }

            if (! is_readable($filePath)) {
                throw new \Exception("File is not readable: {$filePath}");
            }

            $fileSizeActual = filesize($filePath);
            if ($fileSizeActual === false) {
                throw new \Exception("Could not determine file size: {$filePath}");
            }

            \Illuminate\Support\Facades\Log::info("Sending backup file to Telegram: {$filePath} (Size: {$fileSizeActual} bytes)");

            // Read file contents
            $fileContents = file_get_contents($filePath);
            if ($fileContents === false) {
                throw new \Exception("Could not read file contents: {$filePath}");
            }

            // Send file to Telegram using sendDocument API
            $response = Http::timeout(300) // timeout of 5 minutes
                ->attach('document', $fileContents, basename($filePath))
                ->post("https://api.telegram.org/bot{$token}/sendDocument", [
                    'chat_id' => $chatId,
                    'caption' => '[' . config('app.name') . '] Backup of: ' . basename($backupName),
                ]);

            \Illuminate\Support\Facades\Log::info('Telegram API response: ' . json_encode($response->json()));

            $result = $response->json();
            $isSuccess = $response->successful() && ($result['ok'] ?? false);

            // Store backup record (only if botId is provided, otherwise return result for chunked files)
            if ($botId) {
                $telegramFileId = null;
                $telegramMessageId = null;

                if ($isSuccess && isset($result['result'])) {
                    $telegramFileId = $result['result']['document']['file_id'] ?? null;
                    $telegramMessageId = $result['result']['message_id'] ?? null;
                }

                TelegramBackup::create([
                    'bot_id' => $botId,
                    'backup_name' => $backupName,
                    'backup_path' => $filePath,
                    'telegram_file_id' => $telegramFileId ? [$telegramFileId] : null, // Store as array
                    'telegram_message_id' => $telegramMessageId ? [$telegramMessageId] : null, // Store as array
                    'telegram_chat_id' => $chatId,
                    'file_size' => $fileSize,
                    'status' => $isSuccess ? 'sent' : 'failed',
                    'error_message' => $isSuccess ? null : ($result['description'] ?? 'Unknown error'),
                    'sent_at' => $isSuccess ? now() : null,
                ]);
            }

            if (! $isSuccess) {
                return $result;
            }

            return $result;
        } catch (\Exception $e) {
            // Store failed backup record
            if ($botId) {
                TelegramBackup::create([
                    'bot_id' => $botId,
                    'backup_name' => $backupName,
                    'backup_path' => $filePath,
                    'telegram_chat_id' => $chatId,
                    'file_size' => $fileSize,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Split the file into chunks and send each chunk to Telegram.
     */
    private function splitAndSendFile($backupFile, int $chunkSize, string $backupName, int $fileSize): ?array
    {
        consoleOutput()->info('Backup file is too large, splitting into chunks of ' . $chunkSize . ' MB.');

        $chunks = (new SplitLargeFile)
            ->execute($backupFile, $chunkSize);

        // Get all active bots from database
        $bots = \FieldTechVN\TelegramBackup\Models\TelegramBot::where('is_active', true)->get();

        if ($bots->isEmpty()) {
            // Fallback to config if no active bots in database
            $token = config('telegram-backup-filamentphp.backup.token') ?? config('backup-telegram.token');
            $chatId = config('telegram-backup-filamentphp.backup.chat_id');

            if (empty($token) || empty($chatId)) {
                consoleOutput()->error('Telegram token or chat ID is not configured.');

                return null;
            }

            // Send chunks using config token/chat
            $allSuccess = true;
            $messageIds = [];
            foreach ($chunks as $chunk) {
                $result = $this->sendFileDirectly($token, $chatId, $chunk, basename($chunk), filesize($chunk), null);
                if (! ($result['ok'] ?? false)) {
                    $allSuccess = false;
                } else {
                    if (isset($result['result']['message_id'])) {
                        $messageIds[] = $result['result']['message_id'];
                    }
                }
            }

            // Clean up chunks
            foreach ($chunks as $chunk) {
                @unlink($chunk);
            }

            return $allSuccess ? ['ok' => true] : ['ok' => false];
        }

        // Send chunks to all active bots and their active chats
        $overallSuccess = true;
        $hasAnySuccess = false;
        $firstMessageId = null;
        $firstChatId = null;
        $firstBotId = null;

        foreach ($bots as $bot) {
            // Get active chats associated with this bot (many-to-many)
            $chats = $bot->chats()->where('is_active', true)->get();
            $chatIds = $chats->pluck('chat_id')->toArray();

            if (empty($chatIds)) {
                // Fallback to config chat_id if no chats configured for this bot
                $configChatId = config('telegram-backup-filamentphp.backup.chat_id');
                if (! empty($configChatId)) {
                    $chatIds = [$configChatId];
                }
            }

            if (empty($chatIds)) {
                continue; // Skip this bot if no chats available
            }

            // Send all chunks to all chats for this bot
            foreach ($chatIds as $chatId) {
                $messageIds = [];
                $fileIds = [];
                $botSuccess = true;

                foreach ($chunks as $chunk) {
                    // Pass null as botId to prevent creating individual records for each chunk
                    $result = $this->sendFileDirectly($bot->bot_token, $chatId, $chunk, basename($chunk), filesize($chunk), null);
                    if (! ($result['ok'] ?? false)) {
                        $botSuccess = false;
                        $overallSuccess = false;
                    } else {
                        $hasAnySuccess = true;
                        if (isset($result['result']['message_id'])) {
                            $messageIds[] = $result['result']['message_id'];
                        }
                        // Collect file IDs from each chunk
                        if (isset($result['result']['document']['file_id'])) {
                            $fileIds[] = $result['result']['document']['file_id'];
                        }
                    }
                }

                // Store single backup record for chunked files with all file IDs
                if ($botSuccess && ! empty($fileIds) && ! empty($messageIds)) {
                    if ($firstMessageId === null) {
                        $firstMessageId = $messageIds[0];
                        $firstChatId = $chatId;
                        $firstBotId = $bot->id;
                    }

                    TelegramBackup::create([
                        'bot_id' => $bot->id,
                        'backup_name' => $backupName,
                        'backup_path' => $backupFile,
                        'telegram_file_id' => $fileIds, // Store all file IDs as array
                        'telegram_message_id' => $messageIds, // Store all message IDs as array
                        'telegram_chat_id' => $chatId,
                        'file_size' => $fileSize,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            }
        }

        // Clean up the chunks after sending
        foreach ($chunks as $chunk) {
            @unlink($chunk);
        }

        return $hasAnySuccess ? ['ok' => true] : ['ok' => false];
    }
}
