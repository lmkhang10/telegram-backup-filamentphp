<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramBackups extends ListRecords
{
    protected static string $resource = TelegramBackupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Backups are created automatically, no manual creation needed
        ];
    }
}
