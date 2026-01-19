# Telegram Backup with FilamentPHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lmkhang10/telegram-backup-filamentphp.svg?style=flat-square)](https://packagist.org/packages/lmkhang10/telegram-backup-filamentphp)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lmkhang10/telegram-backup-filamentphp/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lmkhang10/telegram-backup-filamentphp/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lmkhang10/telegram-backup-filamentphp/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lmkhang10/telegram-backup-filamentphp/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lmkhang10/telegram-backup-filamentphp.svg?style=flat-square)](https://packagist.org/packages/lmkhang10/telegram-backup-filamentphp)



This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

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

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$telegramBackup = new FieldTechVN\TelegramBackup();
echo $telegramBackup->echoPhrase('Hello, FieldTechVN!');
```

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
