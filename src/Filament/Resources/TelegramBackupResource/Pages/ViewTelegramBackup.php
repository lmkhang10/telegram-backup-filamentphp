<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ViewTelegramBackup extends ViewRecord
{
    protected static string $resource = TelegramBackupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download Backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => route('telegram-backup.download', $this->record->id))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->status === 'sent' && !empty($this->record->telegram_file_id))
                ->tooltip('Download backup file from Telegram'),
        ];
    }
}
