<?php

namespace FieldTechVN\TelegramBackup\Http\Controllers;

use FieldTechVN\TelegramBackup\Http\Requests\DownloadTelegramBackupRequest;
use FieldTechVN\TelegramBackup\Models\TelegramBackup;
use FieldTechVN\TelegramBackup\Services\TelegramBackupDownloadService;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelegramBackupController extends Controller
{
    protected TelegramBackupDownloadService $downloadService;

    public function __construct(TelegramBackupDownloadService $downloadService)
    {
        $this->downloadService = $downloadService;
    }

    /**
     * Download backup file from Telegram
     *
     * @param  int  $id
     */
    public function download(DownloadTelegramBackupRequest $request, $id): StreamedResponse
    {
        $backup = TelegramBackup::findOrFail($id);

        try {
            return $this->downloadService->download($backup);
        } catch (\Exception $e) {
            abort(500, 'Download failed: ' . $e->getMessage());
        }
    }
}
