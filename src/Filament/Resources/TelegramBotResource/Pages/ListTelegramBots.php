<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListTelegramBots extends ListRecords
{
    protected static string $resource = TelegramBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->createAnother(false),
        ];
    }
}
