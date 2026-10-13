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
unknown levels/timezones, empty stream-mode paths and paths containing NUL bytes fail during
factory resolution with `InvalidArgumentException`. The factory does not open
the stream until a record passes the level filter; file/permission failures are
reported when writing. Configuration must be application-owned and trusted.
The opt-in private-file policy below adds destination checks, not a path
sandbox, retention or credential redaction.

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

### Optional private file logging

The provider can supply a disabled logger, or a private file-only logger, without
application-specific handlers. These options are additive: omitting them keeps
the 2.0 stream/processor defaults and the static creation API unchanged.

~~~php
'logger' => [
    'enabled' => true,
    'file_only' => true,
    'filename' => '/var/log/my-application/debug.log',
    'level' => 'debug',
    'timezone' => 'UTC',
    'formatter' => 'json',
    'processors' => [],
],
~~~

With `file_only: true`, an absent, null, empty or whitespace-only filename
disables logging; the string `"0"` is a valid relative filename. A provided
destination MUST be a local path: stream schemes, including `php://stdout`,
`php://stderr` and `file://`, are rejected. `enabled: false` also disables
logging. The disabled service remains a `CoiSA\Logger\Logger` backed by
Monolog's NullHandler; it writes nothing, adds no metadata, constructs no sink
or processors and does not resolve a container timezone.

File-only mode uses a constant `application` channel and no processors by
default. The parent directory must already exist, be controlled by the
application and not be group/world-writable. Parent aliases are canonicalized
without following the final file. Directories, devices, final symlinks and
multiply linked files are rejected. Creation is exclusive with a temporary
private umask, which is restored even on failure. Every open/reopen checks
`chmod(0600)` success and actual descriptor permissions. Every append (including
partial writes) verifies regular-file status, effective 0600 and matching
path/descriptor device and inode. Lock, permission or write failures close the
descriptor and raise UnexpectedValueException; no stdout fallback or retry loop
is added. Resets close the file, and changed file identities require revalidation.

These checks target local POSIX-style filesystems with trustworthy permission
metadata and advisory locking. This is **not** a sandbox against hostile
same-UID processes, mutable/untrusted ancestor directories or ACLs that grant
access beyond mode bits. Use a controlled log directory; the library does not
create directories, administer ownership, implement retention or redact secrets.

`processors` accepts a list of finite identifiers, not class names or callables.
An explicit empty list disables all processors. Missing/null selections keep
legacy defaults in stream mode and no processors in file-only mode. Available
identifiers are:

- `psr_message`, `sapi_name`, `process_id`, `uid`, `uuid`;
- `protocol_version`, `session_id`, `request_size`, `client_ip`, `is_https`, `web`;
- `backtrace`, `memory_usage`, `memory_peak_usage`, `load_average`, `stopwatch_timer`.

Select metadata-producing processors only when their content is appropriate.
`formatter` accepts `text` (default) or `json` (one JSON record per line,
without automatic exception stack traces). Formatter output still contains
caller-supplied messages/context; never pass credentials or entire private
event/request objects. Unknown/duplicate processor IDs and invalid option types
fail during immutable configuration construction.

The additional readonly properties are `enabled`, `fileOnly`, `processors`
(normalized Processor enum list, or null for legacy defaults), and `formatter`
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
