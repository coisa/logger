<?php

/**
 * This file is part of coisa/logger.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/logger
 * @link      https://12factor.net/logs
 *
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentor.dev.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\LogLevel as ConfiguredLogLevel;
use CoiSA\Logger\Enum\Formatter;
use CoiSA\Logger\Enum\Processor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\ProcessorInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Factory for creating Logger instances.
 *
 * This factory SHALL produce Logger instances compliant with PSR-3.
 */
final class LoggerFactory
{
    /**
     * Invokes the factory to create a logger instance.
     *
     * The provider MUST register LoggerConfig. Configuration and timezone policy
     * are documented in README.md under Factory configuration.
     *
     * @param ContainerInterface $container The dependency injection container.
     *
     * @return LoggerInterface The constructed Logger instance.
     *
     * @throws \InvalidArgumentException When configuration or a needed timezone fallback is invalid.
     */
    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        $config = $container->get(LoggerConfig::class);
        if (!$config instanceof LoggerConfig) {
            throw new \InvalidArgumentException('The logger configuration service must be a LoggerConfig instance.');
        }
        return self::createLogger(
            level: $config->level,
            filename: $config->filename,
            timezone: $config->enabled ? $config->timezone ?? $this->resolveContainerTimezone($container) : null,
            processors: $config->processors,
            formatter: $config->formatter,
            enabled: $config->enabled,
        );
    }

    /**
     * Resolves and normalizes the legacy container timezone only when needed.
     *
     * @param ContainerInterface $container The container providing an optional DateTimeZone service.
     *
     * @return \DateTimeZone|null The fallback timezone, or null to use the process default.
     *
     * @throws \InvalidArgumentException When the timezone service is not a valid name, object or null.
     */
    private function resolveContainerTimezone(ContainerInterface $container): ?\DateTimeZone
    {
        $timezone = $container->has(\DateTimeZone::class) ? $container->get(\DateTimeZone::class) : null;
        if (is_string($timezone)) {
            try {
                $timezone = new \DateTimeZone($timezone);
            } catch (\Exception | \ValueError $exception) {
                throw new \InvalidArgumentException('logger.timezone must be a valid timezone name.', 0, $exception);
            }
        }
        if ($timezone !== null && !$timezone instanceof \DateTimeZone) {
            throw new \InvalidArgumentException(
                'logger.timezone or the container timezone must be a DateTimeZone, timezone name or null.',
            );
        }

        return $timezone;
    }

    /**
     * Creates a Logger instance with optional parameters.
     *
     * This method SHALL configure a Logger with a specified logging level,
     * output stream, and timezone.
     *
     * @param string|ConfiguredLogLevel $level A legacy Monolog level string or a canonical PSR-3 enum.
     * @param string $filename The output stream or file path for logs.
     * @param \DateTimeZone|null $timezone The timezone for log timestamps.
     * @param list<Processor|class-string<ProcessorInterface>>|null $processors Cases/classes, or null for defaults.
     * @param Formatter $formatter The output representation.
     * @param bool $enabled Whether this concrete wrapper may emit records.
     *
     * @return Logger The configured concrete Logger instance.
     *
     * @throws \InvalidArgumentException When Monolog rejects the level or output stream.
     */
    public static function createLogger(
        string|ConfiguredLogLevel $level = LogLevel::DEBUG,
        string $filename = LoggerConfig::DEFAULT_FILENAME,
        ?\DateTimeZone $timezone = null,
        ?array $processors = null,
        Formatter $formatter = Formatter::TEXT,
        bool $enabled = true,
    ): Logger {
        $monolog = self::createMonologLogger(
            name: (string) gethostname(),
            level: $level instanceof ConfiguredLogLevel ? $level->value : $level,
            filename: $filename,
            timezone: $timezone,
            processors: $processors,
            formatter: $formatter,
            enabled: $enabled,
        );

        return new Logger($monolog);
    }

    /**
     * Creates a Monolog logger with optional handlers and processors.
     *
     * This method SHALL initialize a Monolog instance with the given name,
     * logging level, output stream, and timezone.
     *
     * @param string $name The logger name.
     * @param string $level The minimum logging level (e.g., DEBUG, INFO).
     * @param string $filename The output stream or file path for logs.
     * @param \DateTimeZone|null $timezone The timezone for log timestamps.
     * @param list<Processor|class-string<ProcessorInterface>>|null $processors Cases/classes, or null for defaults.
     * @param Formatter $formatter The output representation.
     * @param bool $enabled Whether records may reach a stream.
     *
     * @return MonologLogger The configured Monolog logger instance.
     */
    private static function createMonologLogger(
        string $name,
        string $level = LogLevel::DEBUG,
        string $filename = LoggerConfig::DEFAULT_FILENAME,
        ?\DateTimeZone $timezone = null,
        ?array $processors = null,
        Formatter $formatter = Formatter::TEXT,
        bool $enabled = true,
    ): MonologLogger {
        $handler = $enabled ? new StreamHandler($filename, $level) : new NullHandler();
        if ($enabled && $formatter === Formatter::JSON) {
            $handler->setFormatter(new JsonFormatter(includeStacktraces: false));
        }
        return new MonologLogger(
            name: $name,
            handlers: [$handler],
            processors: $enabled ? array_map(
                static fn (Processor|string $processor): ProcessorInterface => new (
                    $processor instanceof Processor ? $processor->value : $processor
                )(),
                $processors ?? Processor::cases(),
            ) : [],
            timezone: $timezone,
        );
    }
}
