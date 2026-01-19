<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource\Pages;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
