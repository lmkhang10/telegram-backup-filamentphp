<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramChatResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramChatResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramChats extends ListRecords
{
    protected static string $resource = TelegramChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make()->createAnother(false),
        ];
    }
}
