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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class LogEventListener.
 *
 * A listener responsible for logging events using a PSR-3 compatible logger.
 *
 * This class handles any object passed as an event and logs it with a structured message.
 * If the event object has a `getName` method, it will be used to retrieve the event name.
 *
 * @package CoiSA\Logger
 */
final class LogEventListener
{
    /**
     * Default log message template.
     *
     * The `{name}` placeholder will be replaced by the event name or class,
     * and `{event}` will include the event object.
     */
    public const DEFAULT_MESSAGE = '[EVENT: {name}] {event}';

    /**
     * PSR-3 compatible logger instance.
     */
    private LoggerInterface $logger;

    /**
     * Log message template.
     */
    private string $message;

    /**
     * LogEventListener constructor.
     *
     * Initializes the event listener with a logger and a log message template.
     *
     * @param null|LoggerInterface $logger  The logger instance to use. Defaults to NullLogger.
     * @param null|string          $message The log message template. Defaults to DEFAULT_MESSAGE.
     */
    public function __construct(?LoggerInterface $logger = null, ?string $message = null)
    {
        $this->logger  = $logger ?? new NullLogger();
        $this->message = $message ?? self::DEFAULT_MESSAGE;
    }

    /**
     * Handles an event and logs it using the configured logger.
     *
     * This method extracts the event name (using the `getName` method if available)
     * and logs the event object with the configured message template.
     *
     * @param object $event the event object to log
     */
    public function __invoke(object $event): void
    {
        $name = \get_class($event);

        if (method_exists($event, 'getName')) {
            $name = $event->getName();
        }

        $this->logger->info($this->message, compact('name', 'event'));
    }
}
