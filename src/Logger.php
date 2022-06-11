<?php

declare(strict_types=1);

/**
 * This file is part of coisa/logger.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/factory
 * @copyright Copyright (c) 2022 Felipe Sayão Lobato Abreu <github@mentor.dev.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Class Logger.
 *
 * @package CoiSA\Logger
 */
final class Logger extends AbstractLogger
{
    /**
     * @const string Default log message format.
     */
    public const DEFAULT_FORMAT = '%s %s: %s %s';

    /**
     * @const string Default timestamp format.
     */
    public const DEFAULT_DATETIME_FORMAT = \DateTimeInterface::ATOM;

    /**
     * @const string[] Log levels which should be sent to STDERR.
     */
    private const ERRORS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
    ];

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = []): void
    {
        $dateTime = date(self::DEFAULT_DATETIME_FORMAT);
        $message  = sprintf(self::DEFAULT_FORMAT, $dateTime, mb_strtoupper($level), $message, json_encode($context));
        $stream   = \in_array($level, self::ERRORS, true) ? STDERR : STDOUT;

        fwrite($stream, $message . PHP_EOL);
    }
}
