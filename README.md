# CoiSA Logger - Twelve-Factor PSR-3 Logger

[![PHP Version][ico-php]][link-packagist]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Total Downloads][ico-downloads]][link-downloads]

`coisa/logger` is a [PSR-3 Logger](https://www.php-fig.org/psr/psr-3/) implementation that uses the [Monolog](https://seldaek.github.io/monolog/) to create a [Twelve-Factor Logger](https://12factor.net/logs).

## Installation

### Using composer

```sh
$ composer require coisa/logger
```

## Factory configuration

`LoggerConfigFactory` optionally resolves `FastForward\Config\ConfigInterface` from
the PSR-11 container. Register that service with a nested `logger` section:

```php
use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;

$config = new ArrayConfig([
    'logger' => [
        'level' => 'debug',
        'filename' => '/var/log/my-application/debug.log',
        'timezone' => 'UTC',
    ],
]);
// Bind $config as ConfigInterface::class in your application's container.
```

All keys are optional. Without a configuration service or section, behavior
remains `debug`, `php://stdout`, and the container's `DateTimeZone` service (or
the process default timezone when that service is absent). Missing and `null`
settings use the same defaults: `debug` for level, `php://stdout` for filename,
and the lazily resolved container/process fallback for timezone. A non-null
timezone name or `DateTimeZone` instance takes precedence over the container.

`LoggerConfig` is a readonly snapshot constructed from `ConfigInterface`. Its
public `level`, `filename` and `timezone` properties contain validated values;
`level` uses the backed `CoiSA\Logger\Enum\LogLevel` enum. A non-null timezone
never resolves the container's timezone service; `null` allows that fallback.
`LoggerConfig::DEFAULT_FILENAME` owns the `php://stdout` default shared by the
configuration and logger creation APIs.

Non-null configured levels accept only the eight canonical lowercase PSR-3 names. Invalid types,
unknown levels/timezones, empty paths and paths containing NUL bytes fail during
factory resolution with `InvalidArgumentException`. The factory does not open
the stream until a record passes the level filter; file/permission failures are
reported by Monolog when writing. Configuration must be application-owned and
trusted: no path sandbox, rotation, retention or credential redaction is added.

The Laminas `ConfigProvider`, container-interop provider and Laravel adapter
register `LoggerConfigFactory` for `LoggerConfig`, alongside the logger factory
and existing aliases. `LoggerFactory` consumes that registered configuration
service; custom containers must provide it too. `LoggerConfigFactory` resolves
optional `ConfigInterface` or supplies empty configuration for defaults.
Direct `LoggerFactory::createLogger()` calls accept
a legacy string (including existing uppercase forms) or the package's `LogLevel`
enum; the enum is converted to its string value at the Monolog boundary. These
calls do not resolve configuration. Applications may
route diagnostic files independently of console verbosity; avoid logging
secrets or complete request/event objects.

Symfony Stopwatch 7.2 and 8.x are supported. To exercise both dependency lines:

```sh
composer update 'symfony/stopwatch:^7.2' --with-all-dependencies
composer test
composer update 'symfony/stopwatch:^8.0' --with-all-dependencies
composer test
```

## Credits

- [Felipe Sayão Lobato Abreu][link-author]

## License

Please see [License File](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/coisa/logger.svg?style=flat-square
[ico-php]: https://img.shields.io/packagist/php-v/coisa/logger.svg?style=flat-square
[ico-license]: https://img.shields.io/github/license/coisa/logger.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/coisa/logger.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/coisa/logger
[link-downloads]: https://packagist.org/packages/coisa/logger
[link-author]: https://github.com/coisa
