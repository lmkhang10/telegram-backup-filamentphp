<?php

namespace FieldTechVN\TelegramBackup\Services;

use Exception;
use FieldTechVN\TelegramBackup\Models\TelegramBot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('telegram-backup-filamentphp.api.base_url', 'https://api.telegram.org/bot');
    }

    /**
     * Get bot information from Telegram API
     */
    public function getBotInfo(string $botToken): ?array
    {
        try {
            $response = Http::timeout(config('telegram-backup-filamentphp.api.timeout', 30))
                ->get($this->baseUrl . $botToken . '/getMe');

            if ($response->successful()) {
                return $response->json('result');
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get bot info: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Test bot connection and optionally fetch chats
     */
    public function testConnection(TelegramBot $bot, bool $fetchChats = false): array
    {
        $result = [
            'success' => false,
            'bot_info' => null,
            'chats' => [],
            'created' => 0,
            'attached' => 0,
        ];

        $botInfo = $this->getBotInfo($bot->bot_token);

        if ($botInfo) {
            $bot->update([
                'bot_username' => $botInfo['username'] ?? null,
                'bot_name' => $botInfo['first_name'] ?? null,
            ]);

            $result['success'] = true;
            $result['bot_info'] = $botInfo;

            // Fetch chats if requested
            if ($fetchChats) {
                $chats = $this->fetchChatsFromUpdates($bot);

                // If no chats found from updates, try to get chat from config as fallback
                if (empty($chats)) {
                    $configChatId = config('telegram-backup-filamentphp.backup.chat_id');
                    if ($configChatId) {
                        try {
                            $chatInfo = $this->getChatInfo($bot->bot_token, $configChatId);
                            if ($chatInfo) {
                                $chats[] = [
                                    'chat_id' => (string) $chatInfo['id'],
                                    'chat_type' => $chatInfo['type'] ?? 'private',
                                    'name' => $chatInfo['title'] ?? $chatInfo['first_name'] ?? null,
                                    'username' => $chatInfo['username'] ?? null,
                                    'first_name' => $chatInfo['first_name'] ?? null,
                                    'last_name' => $chatInfo['last_name'] ?? null,
                                    'description' => $chatInfo['description'] ?? null,
                                    'is_active' => true,
                                ];
                                Log::info("Found chat from config: {$configChatId}");
                            }
                        } catch (\Exception $e) {
                            Log::warning("Failed to get chat from config chat_id {$configChatId}: " . $e->getMessage());
                        }
                    }
                }

                $result['chats'] = $chats;

                // Only create/update chats and attach if bot is saved (has an ID)
                if ($bot->exists && $bot->id) {
                    foreach ($chats as $chatData) {
                        // Check if chat already exists
                        $chat = \FieldTechVN\TelegramBackup\Models\TelegramChat::where('chat_id', $chatData['chat_id'])->first();

                        if (! $chat) {
                            // Create new chat
                            $chat = \FieldTechVN\TelegramBackup\Models\TelegramChat::create($chatData);
                            $result['created']++;
                        } else {
                            // Update existing chat
                            $chat->update($chatData);
                        }

                        // Attach to bot if not already attached
                        if (! $bot->chats()->where('telegram_chats.id', $chat->id)->exists()) {
                            $bot->chats()->attach($chat->id);
                            $result['attached']++;
                        }
                    }
                } else {
                    // Bot is not saved yet, just count chats that would be created/attached
                    foreach ($chats as $chatData) {
                        $existingChat = \FieldTechVN\TelegramBackup\Models\TelegramChat::where('chat_id', $chatData['chat_id'])->first();
                        if (! $existingChat) {
                            $result['created']++;
                        }
                        $result['attached']++;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get chat information from Telegram API
     */
    public function getChatInfo(string $botToken, string $chatId): ?array
    {
        try {
            $response = Http::timeout(config('telegram-backup-filamentphp.api.timeout', 30))
                ->get($this->baseUrl . $botToken . '/getChat', [
                    'chat_id' => $chatId,
                ]);

            if ($response->successful()) {
                return $response->json('result');
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get chat info: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Get updates to discover chats / messages (supports optional long polling)
     */
    public function getUpdates(string $botToken, int $offset = 0, int $limit = 100, int $timeout = 0): ?array
    {
        try {
            $params = [
                'offset' => $offset,
                'limit' => $limit,
            ];

            if ($timeout > 0) {
                // Long polling timeout in seconds
                $params['timeout'] = $timeout;
            }

            $httpTimeout = $timeout > 0
                ? $timeout + 5
                : config('telegram-backup-filamentphp.api.timeout', 30);

            $response = Http::timeout($httpTimeout)
                ->get($this->baseUrl . $botToken . '/getUpdates', $params);

            if ($response->successful()) {
                $result = $response->json();

                // Log for debugging
                if (isset($result['result']) && is_array($result['result'])) {
                    Log::info('getUpdates returned ' . count($result['result']) . ' updates');
                } else {
                    Log::warning('getUpdates returned unexpected format: ' . json_encode($result));
                }

                return $result['result'] ?? null;
            }

            $error = $response->json();
            Log::error('getUpdates failed: ' . json_encode($error));

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get updates: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Fetch and create chats from bot updates
     */
    public function fetchChatsFromUpdates(TelegramBot $bot): array
    {
        $chats = [];
        $updates = $this->getUpdates($bot->bot_token, 0, 100);

        if (! $updates || ! is_array($updates)) {
            Log::warning("No updates returned for bot {$bot->id}. Updates: " . json_encode($updates));

            return $chats;
        }

        $discoveredChatIds = [];

        foreach ($updates as $update) {
            $chat = null;

            // Extract chat from different update types
            if (isset($update['message']['chat'])) {
                $chat = $update['message']['chat'];
            } elseif (isset($update['edited_message']['chat'])) {
                $chat = $update['edited_message']['chat'];
            } elseif (isset($update['channel_post']['chat'])) {
                $chat = $update['channel_post']['chat'];
            } elseif (isset($update['edited_channel_post']['chat'])) {
                $chat = $update['edited_channel_post']['chat'];
            } elseif (isset($update['my_chat_member']['chat'])) {
                // Bot was added/removed from a chat
                $chat = $update['my_chat_member']['chat'];
            } elseif (isset($update['chat_member']['chat'])) {
                // Chat member status changed
                $chat = $update['chat_member']['chat'];
            } elseif (isset($update['chat_join_request']['chat'])) {
                // Join request
                $chat = $update['chat_join_request']['chat'];
            }

            if ($chat && isset($chat['id'])) {
                $chatId = (string) $chat['id'];

                // Skip if already processed
                if (in_array($chatId, $discoveredChatIds)) {
                    continue;
                }

                $discoveredChatIds[] = $chatId;

                try {
                    // Get full chat info
                    $chatInfo = $this->getChatInfo($bot->bot_token, $chatId);

                    if ($chatInfo) {
                        $chats[] = [
                            'chat_id' => (string) $chatInfo['id'],
                            'chat_type' => $chatInfo['type'] ?? 'private',
                            'name' => $chatInfo['title'] ?? $chatInfo['first_name'] ?? null,
                            'username' => $chatInfo['username'] ?? null,
                            'first_name' => $chatInfo['first_name'] ?? null,
                            'last_name' => $chatInfo['last_name'] ?? null,
                            'description' => $chatInfo['description'] ?? null,
                            'is_active' => true,
                        ];
                    } else {
                        // If getChat fails, use data from update (fallback)
                        Log::warning("Failed to get full chat info for chat_id: {$chatId}, using update data");
                        $chats[] = [
                            'chat_id' => $chatId,
                            'chat_type' => $chat['type'] ?? 'private',
                            'name' => $chat['title'] ?? $chat['first_name'] ?? null,
                            'username' => $chat['username'] ?? null,
                            'first_name' => $chat['first_name'] ?? null,
                            'last_name' => $chat['last_name'] ?? null,
                            'description' => $chat['description'] ?? null,
                            'is_active' => true,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing chat {$chatId}: " . $e->getMessage());

                    continue;
                }
            }
        }

        Log::info('Fetched ' . count($chats) . " chats from updates for bot {$bot->id}");

        return $chats;
    }

    /**
     * Send a text message to a chat
     */
    public function sendMessage(string $botToken, string $chatId, string $text): ?array
    {
        try {
            $response = Http::timeout(config('telegram-backup-filamentphp.api.timeout', 30))
                ->post($this->baseUrl . $botToken . '/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('sendMessage failed: ' . json_encode($response->json()));

            return null;
        } catch (Exception $e) {
            Log::error('Failed to send message: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Simple long-polling loop similar to node-telegram-bot-api example.
     * Listens for messages and echoes them back for a short period.
     */
    public function startSimpleLongPolling(TelegramBot $bot, int $durationSeconds = 60, int $intervalMs = 3000, int $timeout = 10): void
    {
        $endTime = microtime(true) + $durationSeconds;
        $offset = 0;
        $cacheKey = 'telegram_long_polling:' . $bot->id;

        while (microtime(true) < $endTime) {
            // Check if polling was stopped via cache
            if (! \Illuminate\Support\Facades\Cache::has($cacheKey)) {
                Log::info('Telegram long polling: Stopped by cache check', [
                    'bot_id' => $bot->id,
                    'bot_username' => $bot->bot_username,
                ]);

                break;
            }
            $updates = $this->getUpdates($bot->bot_token, $offset, 100, $timeout);

            if (! is_array($updates) || empty($updates)) {
                // No updates, wait for next interval
                usleep($intervalMs * 1000);

                continue;
            }

            foreach ($updates as $update) {
                $updateId = $update['update_id'] ?? null;
                if ($updateId !== null) {
                    $offset = $updateId + 1;
                }

                // Extract message from different update types
                $message = null;
                if (isset($update['message'])) {
                    $message = $update['message'];
                } elseif (isset($update['channel_post'])) {
                    $message = $update['channel_post'];
                } elseif (isset($update['edited_message'])) {
                    $message = $update['edited_message'];
                } elseif (isset($update['edited_channel_post'])) {
                    $message = $update['edited_channel_post'];
                }

                if (! $message || empty($message['text'] ?? null) || empty($message['chat']['id'] ?? null)) {
                    continue;
                }

                $chatId = (string) $message['chat']['id'];
                $text = (string) $message['text'];

                // Handle /setup command - fetch and store chats
                if (preg_match('/^\/setup\b/i', $text)) {
                    try {
                        // Fetch chats from updates
                        // Get full chat info
                        $chatInfo = $this->getChatInfo($bot->bot_token, $chatId);
                        if ($chatInfo) {
                            $chats[] = [
                                'chat_id' => (string) $chatInfo['id'],
                                'chat_type' => $chatInfo['type'] ?? 'private',
                                'name' => $chatInfo['title'] ?? $chatInfo['first_name'] ?? null,
                                'username' => $chatInfo['username'] ?? null,
                                'first_name' => $chatInfo['first_name'] ?? null,
                                'last_name' => $chatInfo['last_name'] ?? null,
                                'description' => $chatInfo['description'] ?? null,
                                'is_active' => true,
                            ];
                        }

                        if (! empty($chats)) {
                            $created = 0;
                            $attached = 0;
                            $updated = 0;

                            foreach ($chats as $chatData) {
                                // Check if chat already exists
                                $chat = \FieldTechVN\TelegramBackup\Models\TelegramChat::where('chat_id', $chatData['chat_id'])->first();

                                if (! $chat) {
                                    // Create new chat
                                    $chat = \FieldTechVN\TelegramBackup\Models\TelegramChat::create($chatData);
                                    $created++;
                                } else {
                                    // Update existing chat
                                    $chat->update($chatData);
                                    $updated++;
                                }

                                // Attach to bot if not already attached
                                if (! $bot->chats()->where('telegram_chats.id', $chat->id)->exists()) {
                                    $bot->chats()->attach($chat->id);
                                    $attached++;
                                }
                            }

                            $messageText = "Setup complete! Found {$created} new chats, updated {$updated} chats and attached {$attached} chats to this bot.";
                            $this->sendMessage($bot->bot_token, $chatId, $messageText);

                        } else {
                            $this->sendMessage($bot->bot_token, $chatId, 'No chats found in recent updates. Make sure the bot has received messages or is added to groups/channels.');
                            Log::warning('Telegram long polling: /setup found no chats', [
                                'bot_id' => $bot->id,
                            ]);
                        }
                    } catch (\Exception $e) {
                        $errorMessage = 'Failed to fetch chats: ' . $e->getMessage();
                        $this->sendMessage($bot->bot_token, $chatId, $errorMessage);
                        Log::error('Telegram long polling: /setup failed', [
                            'bot_id' => $bot->id,
                            'error' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
                } else {
                    // Echo the message back to the user (for non-setup messages)
                    $this->sendMessage($bot->bot_token, $chatId, 'You wrote: ' . $text);

                    // Handle /start command
                    if (preg_match('/^\/start\b/i', $text)) {
                        $this->sendMessage($bot->bot_token, $chatId, 'Hello! I am a bot using long polling.');
                    }
                }
            }

            // Small delay between polling requests (similar to interval in node example)
            usleep($intervalMs * 1000);
        }
    }

    /**
     * Delete a message from Telegram
     */
    public function deleteMessage(string $botToken, string $chatId, int $messageId): array
    {
        $result = [
            'success' => false,
            'error' => null,
        ];

        try {
            $response = Http::timeout(config('telegram-backup-filamentphp.api.timeout', 10))
                ->post($this->baseUrl . $botToken . '/deleteMessage', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);

            $responseData = $response->json();

            if ($response->successful() && ($responseData['ok'] ?? false)) {
                $result['success'] = true;
                Log::info("Successfully deleted message {$messageId} from chat {$chatId}");
            } else {
                $result['error'] = $responseData['description'] ?? 'Unknown error';
                Log::warning("Failed to delete message {$messageId} from chat {$chatId}: " . $result['error']);
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            Log::error("Error deleting message {$messageId} from chat {$chatId}: " . $e->getMessage());
        }

        return $result;
    }
}
