<?php

namespace FieldTechVN\TelegramBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use FieldTechVN\TelegramBackup\Models\TelegramBot;
use FieldTechVN\TelegramBackup\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StartSimpleLongPollingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes max

    public int $botId;
    public int $durationSeconds;
    public int $intervalMs;
    public int $timeoutSeconds;

    protected const CACHE_PREFIX = 'telegram_long_polling:';

    /**
     * Create a new job instance.
     */
    public function __construct(int $botId, int $durationSeconds = 60, int $intervalMs = 3000, int $timeoutSeconds = 10)
    {
        $this->botId = $botId;
        $this->durationSeconds = $durationSeconds;
        $this->intervalMs = $intervalMs;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $bot = TelegramBot::find($this->botId);

        if (!$bot || !$bot->is_active) {
            Log::info("StartSimpleLongPollingJob: Bot not found or inactive", [
                'bot_id' => $this->botId,
            ]);
            $this->clearCache();
            return;
        }

        // Set cache to show active status
        $cacheKey = self::CACHE_PREFIX . $bot->id;
        Cache::put($cacheKey, [
            'bot_id' => $bot->id,
            'bot_username' => $bot->bot_username,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(10)); // Cache expires after 10 minutes

        try {
            /** @var TelegramService $service */
            $service = app(TelegramService::class);
            $service->startSimpleLongPolling(
                $bot,
                durationSeconds: $this->durationSeconds,
                intervalMs: $this->intervalMs,
                timeout: $this->timeoutSeconds,
            );
        } finally {
            // Always clear cache when finished
            $this->clearCache();
        }

        Log::info("StartSimpleLongPollingJob: Long polling session finished", [
            'bot_id' => $bot->id,
            'bot_username' => $bot->bot_username,
        ]);
    }

    /**
     * Clear cache for this bot
     */
    protected function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . $this->botId);
    }
}
