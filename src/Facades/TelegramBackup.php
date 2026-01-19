<?php

namespace FieldTechVN\TelegramBackup\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \FieldTechVN\TelegramBackup\TelegramBackup
 */
class TelegramBackup extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \FieldTechVN\TelegramBackup\TelegramBackup::class;
    }
}
