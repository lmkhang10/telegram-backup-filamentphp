<?php

namespace FieldTechVN\TelegramBackup\Services;

use Exception;
use FieldTechVN\TelegramBackup\Models\TelegramBackup;
use Illuminate\Support\Facades\Http;

class TelegramBackupDownloadService
{
    protected string $baseUrl;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('telegram-backup-filamentphp.api.base_url', 'https://api.telegram.org/bot');
        $this->timeout = config('telegram-backup-filamentphp.api.timeout', 30);
    }

    /**
     * Download backup file(s) from Telegram
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws Exception
     */
    public function download(TelegramBackup $backup)
    {
        if (! $backup->telegram_file_id) {
            throw new Exception('Telegram file ID is not available for this backup.');
        }

        if (! $backup->bot) {
            throw new Exception('Bot information is not available.');
        }

        $fileIds = is_array($backup->telegram_file_id)
            ? $backup->telegram_file_id
            : [$backup->telegram_file_id];

        // Single file - download directly
        if (count($fileIds) === 1) {
            return $this->downloadSingleFile($backup, $fileIds[0]);
        }

        // Multiple files - download chunks, merge, then return
        return $this->downloadAndMergeChunks($backup, $fileIds);
    }

    /**
     * Download a single file from Telegram
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws Exception
     */
    protected function downloadSingleFile(TelegramBackup $backup, string $fileId)
    {
        $filePath = $this->getFilePath($backup->bot->bot_token, $fileId);
        $fileContent = $this->downloadFileContent($backup->bot->bot_token, $filePath);

        return response()->streamDownload(function () use ($fileContent) {
            echo $fileContent;
        }, $backup->backup_name, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * Download multiple chunks and merge them
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws Exception
     */
    protected function downloadAndMergeChunks(TelegramBackup $backup, array $fileIds)
    {
        $tempDir = $this->createTempDirectory($backup->id);
        $chunkFiles = [];
        $mergedContent = null;

        try {
            // Download all chunks
            foreach ($fileIds as $index => $fileId) {
                $filePath = $this->getFilePath($backup->bot->bot_token, $fileId, $index + 1);
                $chunkContent = $this->downloadFileContent($backup->bot->bot_token, $filePath);

                $chunkFile = $tempDir . '/chunk_' . str_pad($index, 4, '0', STR_PAD_LEFT);
                file_put_contents($chunkFile, $chunkContent);
                $chunkFiles[] = $chunkFile;
            }

            // Merge chunks (this will clean up chunks and merged file, but not the temp dir)
            $mergedContent = $this->mergeChunks($chunkFiles, $tempDir, $backup->backup_name);

            // Return merged file as download
            return response()->streamDownload(function () use ($mergedContent) {
                echo $mergedContent;
            }, $backup->backup_name, [
                'Content-Type' => 'application/octet-stream',
            ]);

        } catch (Exception $e) {
            // Clean up any remaining temp files on error
            $this->cleanupTempDirectory($tempDir);

            throw $e;
        } finally {
            // Safety net: Ensure temp directory is cleaned up if it still exists
            // (mergeChunks cleans up on success, but ensure cleanup if something went wrong)
            if (is_dir($tempDir)) {
                $this->cleanupTempDirectory($tempDir);
            }
        }
    }

    /**
     * Get file path from Telegram API
     *
     * @param  int|null  $chunkNumber  For error messages
     *
     * @throws Exception
     */
    protected function getFilePath(string $botToken, string $fileId, ?int $chunkNumber = null): string
    {
        $response = Http::timeout($this->timeout)
            ->get($this->baseUrl . $botToken . '/getFile', [
                'file_id' => $fileId,
            ]);

        if (! $response->successful()) {
            $errorMsg = $chunkNumber
                ? "Failed to get file path from Telegram API for chunk {$chunkNumber}."
                : 'Failed to get file path from Telegram API.';

            throw new Exception($errorMsg);
        }

        $fileInfo = $response->json('result');
        $filePath = $fileInfo['file_path'] ?? null;

        if (! $filePath) {
            $errorMsg = $chunkNumber
                ? "File path not found in Telegram response for chunk {$chunkNumber}."
                : 'File path not found in Telegram response.';

            throw new Exception($errorMsg);
        }

        return $filePath;
    }

    /**
     * Download file content from Telegram
     *
     * @throws Exception
     */
    protected function downloadFileContent(string $botToken, string $filePath): string
    {
        $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

        $response = Http::timeout(300)->get($fileUrl);

        if (! $response->successful()) {
            throw new Exception('Failed to download file from Telegram.');
        }

        return $response->body();
    }

    /**
     * Merge chunk files into a single file
     *
     * @return string Merged file content
     *
     * @throws Exception
     */
    protected function mergeChunks(array $chunkFiles, string $tempDir, string $backupName): string
    {
        $mergedFile = $tempDir . '/merged_' . $backupName;
        $mergedHandle = fopen($mergedFile, 'wb');

        if (! $mergedHandle) {
            throw new Exception('Failed to create merged file.');
        }

        try {
            foreach ($chunkFiles as $chunkFile) {
                if (! file_exists($chunkFile)) {
                    continue; // Skip if chunk file doesn't exist
                }

                $chunkHandle = fopen($chunkFile, 'rb');
                if ($chunkHandle) {
                    try {
                        while (! feof($chunkHandle)) {
                            $chunk = fread($chunkHandle, 8192);
                            if ($chunk !== false) {
                                fwrite($mergedHandle, $chunk);
                            }
                        }
                    } finally {
                        fclose($chunkHandle);
                    }
                    // Clean up chunk file after successful merge
                    @unlink($chunkFile);
                }
            }
        } catch (Exception $e) {
            // Close handle and rethrow
            fclose($mergedHandle);
            // Clean up merged file if it exists
            if (file_exists($mergedFile)) {
                @unlink($mergedFile);
            }

            throw $e;
        }

        fclose($mergedHandle);

        // Read merged file content
        if (! file_exists($mergedFile)) {
            throw new Exception('Merged file was not created successfully.');
        }

        $mergedContent = file_get_contents($mergedFile);

        // Clean up merged file and temp directory after successful read
        @unlink($mergedFile);
        @rmdir($tempDir);

        return $mergedContent;
    }

    /**
     * Create temporary directory for file operations
     *
     * @throws Exception
     */
    protected function createTempDirectory(int $backupId): string
    {
        $tempDir = storage_path('app/temp/telegram-backup-' . $backupId);

        if (! is_dir($tempDir)) {
            if (! mkdir($tempDir, 0755, true)) {
                throw new Exception('Failed to create temporary directory.');
            }
        }

        return $tempDir;
    }

    /**
     * Clean up temporary directory
     */
    protected function cleanupTempDirectory(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }
}
