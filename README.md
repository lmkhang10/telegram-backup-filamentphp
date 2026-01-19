# Telegram Backup with FilamentPHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lmkhang10/telegram-backup-filamentphp.svg?style=flat-square)](https://packagist.org/packages/lmkhang10/telegram-backup-filamentphp)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lmkhang10/telegram-backup-filamentphp/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lmkhang10/telegram-backup-filamentphp/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lmkhang10/telegram-backup-filamentphp/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lmkhang10/telegram-backup-filamentphp/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lmkhang10/telegram-backup-filamentphp.svg?style=flat-square)](https://packagist.org/packages/lmkhang10/telegram-backup-filamentphp)



A Laravel package that integrates Telegram backup functionality with FilamentPHP 3+. This package provides Filament resources for managing Telegram bots, chats, and backups, allowing you to automatically send Laravel backups to Telegram.

## Installation

You can install the package via composer:

```bash
composer require lmkhang10/telegram-backup-filamentphp
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="telegram-backup-filamentphp-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="telegram-backup-filamentphp-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="telegram-backup-filamentphp-views"
```

## Usage

### Register Resources in Your Filament Panel

In your Filament panel provider (e.g., `app/Providers/Filament/AdminPanelProvider.php`), add the Telegram resources:

```php
use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource;
use FieldTechVN\TelegramBackup\Filament\Resources\TelegramChatResource;
use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... other configuration
        ->resources([
            TelegramBotResource::class,
            TelegramChatResource::class,
            TelegramBackupResource::class,
        ])
        ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
            return $builder
                ->items([
                    // ... your other navigation items
                ])
                ->groups([
                    // Telegram group
                    NavigationGroup::make('TelegramGroup')
                        ->label(__('admin.nav.telegram.group'))
                        ->items([
                            NavigationItem::make('TelegramBotResource')
                                ->label(TelegramBotResource::getNavigationLabel())
                                ->icon(TelegramBotResource::getNavigationIcon())
                                ->url(fn(): string => TelegramBotResource::getUrl())
                                ->sort(1),

                            NavigationItem::make('TelegramChatResource')
                                ->label(TelegramChatResource::getNavigationLabel())
                                ->icon(TelegramChatResource::getNavigationIcon())
                                ->url(fn(): string => TelegramChatResource::getUrl())
                                ->sort(2),

                            NavigationItem::make('TelegramBackupResource')
                                ->label(TelegramBackupResource::getNavigationLabel())
                                ->icon(TelegramBackupResource::getNavigationIcon())
                                ->url(fn(): string => TelegramBackupResource::getUrl())
                                ->sort(3),
                        ]),
                ]);
        });
}
```

### Features

- **Telegram Bot Management**: Create and manage Telegram bots for sending backups
- **Chat Management**: Manage Telegram chats/channels where backups are sent
- **Backup Tracking**: View and manage backups sent to Telegram
- **Automatic Backup Sending**: Integrates with Spatie Laravel Backup to automatically send backups to Telegram
- **Large File Splitting**: Automatically splits large backup files to comply with Telegram's file size limits

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [barryle](https://github.com/lmkhang10)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
