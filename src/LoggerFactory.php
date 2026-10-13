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
     * The provider MUST register LoggerConfig. A container timezone SHALL be
     * resolved lazily when the normalized configuration timezone is null.
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
            timezone: $config->timezone ?? $this->resolveContainerTimezone($container),
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
     *
     * @return LoggerInterface The configured Logger instance.
     *
     * @throws \InvalidArgumentException When Monolog rejects the level or output stream.
     */
    public static function createLogger(
        string|ConfiguredLogLevel $level = LogLevel::DEBUG,
        string $filename = LoggerConfig::DEFAULT_FILENAME,
        ?\DateTimeZone $timezone = null,
    ): LoggerInterface {
        $monolog = self::createMonologLogger(
            name: (string) gethostname(),
            level: $level instanceof ConfiguredLogLevel ? $level->value : $level,
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
        string $filename = LoggerConfig::DEFAULT_FILENAME,
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
    private static function getDefaultHandlers(string $level, string $filename = LoggerConfig::DEFAULT_FILENAME): array
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
