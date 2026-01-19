<?php

namespace FieldTechVN\TelegramBackup;

use FieldTechVN\TelegramBackup\Commands\TelegramBackupCommand;
use FieldTechVN\TelegramBackup\Services\SendBackupFile;
use FieldTechVN\TelegramBackup\Services\TelegramBackupService;
use FieldTechVN\TelegramBackup\Testing\TestsTelegramBackup;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TelegramBackupServiceProvider extends PackageServiceProvider
{
    public static string $name = 'telegram-backup-filamentphp';

    public static string $viewNamespace = 'telegram-backup-filamentphp';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('lmkhang10/telegram-backup-filamentphp');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TelegramBackupService::class);
    }

    public function packageBooted(): void
    {
        // Asset Registration
        $assets = $this->getAssets();
        if (! empty($assets)) {
            FilamentAsset::register(
                $assets,
                $this->getAssetPackageName()
            );
        }

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            $stubsPath = dirname(__DIR__) . '/stubs/';
            $filesystem = app(Filesystem::class);

            if (is_dir($stubsPath) && $filesystem->exists($stubsPath)) {
                try {
                    $files = $filesystem->files($stubsPath);

                    // Only publish if there are actual stub files (not just .gitkeep)
                    $stubFiles = array_filter($files, function ($file) {
                        $filename = $file->getFilename();

                        return $filename !== '.gitkeep' &&
                               ($file->getExtension() === 'php' ||
                                strpos($filename, '.stub') !== false);
                    });

                    if (! empty($stubFiles)) {
                        foreach ($stubFiles as $file) {
                            $this->publishes([
                                $file->getRealPath() => base_path("stubs/telegram-backup-filamentphp/{$file->getFilename()}"),
                            ], 'telegram-backup-filamentphp-stubs');
                        }
                    }
                } catch (\Exception $e) {
                    // Silently fail if stubs directory doesn't exist or is empty
                    // This is expected if no stubs are provided
                }
            }
        }

        // Register Filament Resources
        $this->registerFilamentResources();

        // Register Routes
        $this->registerRoutes();

        // Register Backup Event Listeners
        $this->registerBackupEventListeners();

        // Testing
        Testable::mixin(new TestsTelegramBackup);
    }

    /**
     * Register Filament Resources
     */
    protected function registerFilamentResources(): void
    {
        // Filament resources are auto-discovered if they extend Resource
        // No manual registration needed for Filament v3+
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
    }

    /**
     * Register Backup Event Listeners
     */
    protected function registerBackupEventListeners(): void
    {
        // Check if Spatie Laravel Backup package is installed
        if (! class_exists(\Spatie\Backup\Events\BackupWasSuccessful::class)) {
            Log::warning('Telegram Backup: Spatie Laravel Backup package is not installed. Backup-related features will not work.');

            return;
        }

        // Register backup file listener (SendBackupFile)
        Event::listen(
            \Spatie\Backup\Events\BackupWasSuccessful::class,
            SendBackupFile::class
        );

        // Register backup event listeners if backup notifications are enabled
        if (config('telegram-backup-filamentphp.backup.enabled', false)) {
            $backupService = app(TelegramBackupService::class);

            Event::listen(
                \Spatie\Backup\Events\BackupWasSuccessful::class,
                function ($event) use ($backupService) {
                    $backupService->notifyBackupSuccess($event);
                }
            );

            Event::listen(
                \Spatie\Backup\Events\BackupHasFailed::class,
                function ($event) use ($backupService) {
                    $backupService->notifyBackupFailed($event);
                }
            );

            Event::listen(
                \Spatie\Backup\Events\CleanupWasSuccessful::class,
                function ($event) use ($backupService) {
                    $backupService->notifyCleanupSuccess($event);
                }
            );

            Event::listen(
                \Spatie\Backup\Events\CleanupHasFailed::class,
                function ($event) use ($backupService) {
                    $backupService->notifyCleanupFailed($event);
                }
            );

            Event::listen(
                \Spatie\Backup\Events\HealthyBackupWasFound::class,
                function ($event) use ($backupService) {
                    $backupService->notifyHealthyBackup($event);
                }
            );

            Event::listen(
                \Spatie\Backup\Events\UnhealthyBackupWasFound::class,
                function ($event) use ($backupService) {
                    $backupService->notifyUnhealthyBackup($event);
                }
            );
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'lmkhang10/telegram-backup-filamentphp';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        $basePath = dirname(__DIR__);
        $assets = [];

        // Only register assets if they exist
        $cssPath = $basePath . '/resources/dist/telegram-backup-filamentphp.css';
        $jsPath = $basePath . '/resources/dist/telegram-backup-filamentphp.js';

        if (file_exists($cssPath)) {
            $assets[] = Css::make('telegram-backup-filamentphp-styles', $cssPath);
        }

        if (file_exists($jsPath)) {
            $assets[] = Js::make('telegram-backup-filamentphp-scripts', $jsPath);
        }

        // AlpineComponent::make('telegram-backup-filamentphp', $basePath . '/resources/dist/components/telegram-backup-filamentphp.js'),

        return $assets;
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            TelegramBackupCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            '2026_01_13_093400_create_telegram_bots_table',
            '2026_01_13_093401_create_telegram_chats_table',
            '2026_01_13_093404_create_telegram_backups_table',
            '2026_01_13_093405_modify_telegram_file_id_to_json_in_telegram_backups_table',
        ];
    }
}
