<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource;
use FieldTechVN\TelegramBackup\Services\TelegramService;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class EditTelegramBot extends EditRecord
{
    protected static string $resource = TelegramBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $service = app(TelegramService::class);
                    $result = $service->testConnection($this->record, false);
                    
                    if ($result['success']) {
                        // Refresh the form to show updated chats
                        $this->form->fill(array_merge($this->form->getState(), [
                            'bot_username' => $result['bot_info']['username'] ?? null,
                            'bot_name' => $result['bot_info']['first_name'] ?? null,
                        ]));

                        $message = 'Connection successful!';
                        
                        Notification::make()
                            ->title('Connection successful!')
                            ->body($message)
                            ->success()
                            ->send();
                            
                        // Refresh the record to show updated chats
                        $this->record->refresh();
                    } else {
                        Notification::make()
                            ->title('Connection failed!')
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Test Connection')
                ->modalDescription('This will test the bot connection.'),
        ];
    }
}
