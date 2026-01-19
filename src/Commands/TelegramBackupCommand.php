<?php

namespace FieldTechVN\TelegramBackup\Commands;

use Illuminate\Console\Command;

class TelegramBackupCommand extends Command
{
    public $signature = 'telegram-backup-filamentphp';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
