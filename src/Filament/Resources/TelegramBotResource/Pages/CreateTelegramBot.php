<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource;
use FieldTechVN\TelegramBackup\Services\TelegramService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;

class CreateTelegramBot extends CreateRecord
{
    protected static string $resource = TelegramBotResource::class;

    protected array $fetchedChats = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $data = $this->form->getState();
                    
                    if (empty($data['bot_token'])) {
                        Notification::make()
                            ->title('Bot Token is required')
                            ->body('Please enter a bot token before testing connection.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Create temporary bot instance for testing
                    $tempBot = new \FieldTechVN\TelegramBackup\Models\TelegramBot();
                    $tempBot->bot_token = $data['bot_token'];
                    
                    $service = app(TelegramService::class);
                    $result = $service->testConnection($tempBot, false);
                    
                    if ($result['success']) {
                        // Update form fields with bot info
                        $formData = array_merge($data, [
                            'bot_token' => $data['bot_token'],
                            'bot_username' => $result['bot_info']['username'] ?? null,
                            'bot_name' => $result['bot_info']['first_name'] ?? null,
                        ]);

                        // Add fetched chats to form
                        if (!empty($result['chats'])) {
                            $formData['fetched_chats'] = array_map(function ($chat) {
                                return [
                                    'chat_id' => $chat['chat_id'],
                                    'name' => $chat['name'],
                                    'username' => $chat['username'],
                                    'chat_type' => $chat['chat_type'],
                                ];
                            }, $result['chats']);
                        }

                        $this->form->fill($formData);

                        $message = 'Bot information has been retrieved and filled in.';

                        Notification::make()
                            ->title('Connection successful!')
                            ->body($message)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Connection failed!')
                            ->body('Unable to connect to Telegram API. Please check your bot token.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Test connection and fetch chats before saving
        if (empty($data['bot_token'])) {
            throw ValidationException::withMessages([
                'bot_token' => 'Bot token is required.',
            ]);
        }

        // Create temporary bot instance for testing
        $tempBot = new \FieldTechVN\TelegramBackup\Models\TelegramBot();
        $tempBot->bot_token = $data['bot_token'];
        
        $service = app(TelegramService::class);
        $result = $service->testConnection($tempBot, false);
        
        if (!$result['success']) {
            throw ValidationException::withMessages([
                'bot_token' => 'Failed to connect to Telegram API. Please verify your bot token is correct.',
            ]);
        }

        // Auto-fill bot username and name from API response
        $data['bot_username'] = $result['bot_info']['username'] ?? null;
        $data['bot_name'] = $result['bot_info']['first_name'] ?? null;

        // Store fetched chats data for afterCreate hook
        $this->fetchedChats = $result['chats'] ?? [];

        return $data;
    }

    protected function afterCreate(): void
    {
        // Attach fetched chats to the created bot
        if (!empty($this->fetchedChats)) {
            $record = $this->record;
            
            foreach ($this->fetchedChats as $chatData) {
                // Check if chat already exists
                $chat = \FieldTechVN\TelegramBackup\Models\TelegramChat::where('chat_id', $chatData['chat_id'])->first();
                
                if (!$chat) {
                    // Create new chat
                    $chat = \FieldTechVN\TelegramBackup\Models\TelegramChat::create($chatData);
                } else {
                    // Update existing chat
                    $chat->update($chatData);
                }

                // Attach to bot if not already attached
                if (!$record->chats()->where('telegram_chats.id', $chat->id)->exists()) {
                    $record->chats()->attach($chat->id);
                }
            }
        }
    }
}
