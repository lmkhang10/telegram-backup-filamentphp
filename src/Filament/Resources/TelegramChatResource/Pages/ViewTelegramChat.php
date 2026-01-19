<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramChatResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramChatResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramChat extends ViewRecord
{
    protected static string $resource = TelegramChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
