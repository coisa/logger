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
settings use the same defaults: `debug` for level,
`LoggerConfig::DEFAULT_FILENAME` for filename, and the lazily resolved
container/process fallback for timezone. A non-null timezone name or
`DateTimeZone` instance takes precedence over the container for enabled logging.
See [Optional logging and record configuration](#optional-logging-and-record-configuration)
for disabled logging, formatters and processors.

`LoggerConfig` is a readonly snapshot constructed from `ConfigInterface`. Its
public `level`, `filename` and `timezone` properties contain validated values;
`level` uses the backed `CoiSA\Logger\Enum\LogLevel` enum.
`LoggerConfig::DEFAULT_FILENAME` owns the `php://stdout` default shared by the
configuration and logger creation APIs.

Non-null configured levels accept only the eight canonical lowercase PSR-3 names. Invalid types,
unknown levels/timezones, empty paths and paths containing NUL bytes fail during
factory resolution with `InvalidArgumentException`. The factory does not open
the stream until a record passes the level filter; file/permission failures are
reported when writing. Configuration must be application-owned and trusted.
The configured coding standard uses `mb_trim()` for filename validation;
`symfony/polyfill-mbstring` supplies it when native mbstring functions are absent,
without making the extension a platform requirement.
Destinations and their permissions are administered by the application/operator;
the library does not impose a filesystem policy or redact credentials.

The Laminas `ConfigProvider`, container-interop provider and Laravel adapter
register `LoggerConfigFactory` for `LoggerConfig`, alongside the logger factory
and existing aliases. `LoggerFactory` consumes that registered configuration
service; custom containers must provide it too. `LoggerConfigFactory` resolves
optional `ConfigInterface` or supplies empty configuration for defaults.
Direct `LoggerFactory::createLogger()` calls accept
a legacy string (including existing uppercase forms) or the package's `LogLevel`
enum; the enum is converted to its string value at the Monolog boundary. These
calls do not resolve configuration. The original first three arguments remain
unchanged; optional trailing `processors`, `formatter` and `enabled` arguments
share the same construction path used by the factory. Applications may
route diagnostic files independently of console verbosity; avoid logging
secrets or complete request/event objects.

### Optional logging and record configuration

The provider can supply a disabled logger or configure Monolog's standard
`StreamHandler`. These options are additive: omitting them keeps
the 2.0 stream/processor defaults and the static creation API unchanged.

~~~php
'logger' => [
    'enabled' => true,
    'filename' => '/var/log/my-application/debug.log',
    'level' => 'debug',
    'timezone' => 'UTC',
    'formatter' => 'json',
    'processors' => [],
],
~~~

Set `enabled: false` to return a `CoiSA\Logger\Logger` backed by Monolog's
`NullHandler`. It discards records without opening a destination, constructing
processors or consulting the container's timezone fallback. Configured and static
creation use the same `createLogger()` path with the `enabled` flag. The concrete
entry, `LoggerInterface` and `logger` aliases share one instance in both modes;
constructor injection and `withLevel()` remain safe and cannot enable a disabled
logger's output.

Enabled logging uses `StreamHandler` directly. File paths and PHP stream wrappers
are supported; missing/null filenames retain the `php://stdout` default, and
`"0"` is a valid relative filename. File creation, appending and write failures
follow Monolog's behavior. Provision appropriate directory access and file
permissions outside this library. No strict mode-bit, symlink, inode or locking
policy is promised.

`processors` accepts a list of classes implementing
`Monolog\Processor\ProcessorInterface`, including application-owned custom
processors. Each class must be instantiable without constructor arguments.
`CoiSA\Logger\Enum\Processor` cases are also accepted as a convenience and
normalize to their class-name values; the enum is not an extension allowlist.
An explicit empty list disables all processors. Missing/null selections keep
legacy defaults directly from `Processor::cases()` in declaration order. For example:

~~~php
'processors' => [CoiSA\Logger\Enum\Processor::PSR_MESSAGE],
// Equivalent: [Monolog\Processor\PsrLogMessageProcessor::class]
~~~

Available cases are `PSR_MESSAGE`, `SAPI_NAME`, `PROCESS_ID`, `UID`, `UUID`,
`PROTOCOL_VERSION`, `SESSION_ID`, `REQUEST_SIZE`, `CLIENT_IP`, `IS_HTTPS`, `WEB`,
`BACKTRACE`, `MEMORY_USAGE`, `MEMORY_PEAK_USAGE`, `LOAD_AVERAGE` and
`STOPWATCH_TIMER`. The factory instantiates each selected case's class value,
without a duplicated match table.

Select metadata-producing processors only when their content is appropriate.
`formatter` accepts `text` (default) or `json` (one JSON record per line,
without automatic exception stack traces). Formatter output still contains
caller-supplied messages/context; never pass credentials or entire private
event/request objects. Non-implementing/duplicate processor classes and invalid option types
fail during immutable configuration construction.

The additional readonly properties are `enabled`, `processors`
(normalized processor class list, or null for legacy defaults), and `formatter`
(Formatter enum). Configuration remains registered by all three existing
service-provider adapters; no extra application service registration is needed.

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
