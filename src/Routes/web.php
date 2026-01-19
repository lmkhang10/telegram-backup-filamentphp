<?php

use FieldTechVN\TelegramBackup\Http\Controllers\TelegramBackupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('telegram-backup')
    ->name('telegram-backup.')
    ->group(function () {
        Route::get('/download/{id}', [TelegramBackupController::class, 'download'])
            ->name('download');
    });
