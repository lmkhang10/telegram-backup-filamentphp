<?php

use Illuminate\Support\Facades\Route;
use FieldTechVN\TelegramBackup\Http\Controllers\TelegramBackupController;

Route::middleware(["web", "auth"])
    ->prefix("telegram-backup")
    ->name("telegram-backup.")
    ->group(function () {
        Route::get("/download/{id}", [TelegramBackupController::class, "download"])
            ->name("download");
    });
