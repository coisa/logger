<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use Monolog\Handler\AbstractHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Class Logger
 *
 * A PSR-3 compliant logger that wraps around Monolog.
 *
 * This class MUST handle logging operations following PSR-3 standards,
 * SHALL support dynamic log level configuration, and ensure proper
 * initialization of handlers and processors.
 */
final class Logger extends AbstractLogger
{
    /**
     * Constructs a Logger instance.
     *
     * This constructor SHALL ensure the logger is properly configured.
     *
     * @param MonologLogger $logger The Monolog logger instance.
     */
    public function __construct(
        private MonologLogger $logger,
    ) {}

    /**
     * Updates the logging level of the primary StreamHandler.
     *
     * This method SHALL ensure the StreamHandler is updated with the
     * specified log level dynamically.
     *
     * @param string $level The new log level (e.g., DEBUG, INFO).
     *
     * @return self A new instance of the Logger with the updated level.
     */
    public function withLevel(string $level = LogLevel::DEBUG): self
    {
        $logger = clone $this;
        $handlers = $logger->logger->getHandlers();

        foreach ($handlers as $handler) {
            if (!$handler instanceof AbstractHandler) {
                continue;
            }

            $handler->setLevel($level);
        }

        return $logger;
    }

    /**
     * Logs with an arbitrary level.
     *
     * This method MUST forward the log level, message, and context
     * to the underlying Monolog instance.
     *
     * @param mixed $level The log level (e.g., DEBUG, INFO, WARNING).
     * @param string $message The log message.
     * @param array $context Contextual data for the log entry.
     *
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
