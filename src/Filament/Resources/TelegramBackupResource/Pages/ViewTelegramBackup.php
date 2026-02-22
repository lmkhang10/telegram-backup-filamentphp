<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource;
use FieldTechVN\TelegramBackup\Models\TelegramBackup;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

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
                ->url(fn (TelegramBackup $record) => route('telegram-backup.download', $record->id))
                ->openUrlInNewTab()
                ->visible(fn (TelegramBackup $record) => $record->status === 'sent' && ! empty($record->telegram_file_id))
                ->tooltip('Download backup file from Telegram'),
        ];
    }
}
