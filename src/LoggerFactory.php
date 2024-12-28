<?php

declare(strict_types=1);

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

namespace CoiSA\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\LoadAverageProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use MonologProcessorCollection\BacktraceProcessor;
use MonologProcessorCollection\ClientIpProcessor;
use MonologProcessorCollection\IsHttpsProcessor;
use MonologProcessorCollection\ProtocolVersionProcessor;
use MonologProcessorCollection\RequestSizeProcessor;
use MonologProcessorCollection\SapiNameProcessor;
use MonologProcessorCollection\SessionIdProcessor;
use MonologProcessorCollection\UuidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Factory for creating Logger instances.
 *
 * This factory SHALL produce Logger instances compliant with PSR-3.
 * It MUST provide default handlers and processors when none are supplied.
 */
final class LoggerFactory
{
    /**
     * Invokes the factory to create a logger instance.
     *
     * The method SHALL attempt to resolve a timezone from the provided
     * dependency injection container. If no timezone is found, it MAY default
     * to `null`.
     *
     * @param ContainerInterface $container The dependency injection container.
     *
     * @return LoggerInterface The constructed Logger instance.
     */
    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        $timezone = $container->has(\DateTimeZone::class)
            ? $container->get(\DateTimeZone::class)
            : null;

        return self::createLogger(timezone: $timezone);
    }

    /**
     * Creates a Logger instance with optional parameters.
     *
     * This method SHALL configure a Logger with a specified logging level,
     * output stream, and timezone.
     *
     * @param string $level The minimum logging level (e.g., DEBUG, INFO).
     * @param string $filename The output stream or file path for logs.
     * @param \DateTimeZone|null $timezone The timezone for log timestamps.
     *
     * @return LoggerInterface The configured Logger instance.
     */
    public static function createLogger(
        string $level = LogLevel::DEBUG,
        string $filename = 'php://stdout',
        ?\DateTimeZone $timezone = null,
    ): LoggerInterface {
        $monolog = self::createMonologLogger(
            name: (string) gethostname(),
            level: $level,
            filename: $filename,
            timezone: $timezone,
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
     *
     * @return MonologLogger The configured Monolog logger instance.
     */
    private static function createMonologLogger(
        string $name,
        string $level = LogLevel::DEBUG,
        string $filename = 'php://stdout',
        ?\DateTimeZone $timezone = null,
    ): MonologLogger {
        return new MonologLogger(
            name: $name,
            handlers: self::getDefaultHandlers($level, $filename),
            processors: self::getDefaultProcessors(),
            timezone: $timezone,
        );
    }

    /**
     * Returns the default handlers for the logger.
     *
     * This method SHALL configure and return an array of default handlers,
     * including a `StreamHandler` pointing to the specified stream or file.
     *
     * @param string $level The minimum logging level (e.g., DEBUG, INFO).
     * @param string $filename The output stream or file path for logs.
     *
     * @return array<StreamHandler> The default handlers.
     */
    private static function getDefaultHandlers(string $level, string $filename = 'php://stdout'): array
    {
        return [
            new StreamHandler(
                stream: $filename,
                level: $level,
            ),
        ];
    }

    /**
     * Returns the default processors for the logger.
     *
     * This method SHALL configure and return an array of default processors
     * for enriching log entries with metadata (e.g., process ID, memory usage).
     *
     * @return array<callable|ProcessorInterface> The default processors.
     */
    private static function getDefaultProcessors(): array
    {
        return [
            new PsrLogMessageProcessor(),
            new SapiNameProcessor(),
            new ProcessIdProcessor(),
            new UidProcessor(),
            new UuidProcessor(),
            new ProtocolVersionProcessor(),
            new SessionIdProcessor(),
            new RequestSizeProcessor(),
            new ClientIpProcessor(),
            new IsHttpsProcessor(),
            new WebProcessor(),
            new BacktraceProcessor(),
            new MemoryUsageProcessor(),
            new MemoryPeakUsageProcessor(),
            new LoadAverageProcessor(),
            new StopwatchTimerProcessor(),
        ];
    }
}
