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
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\Logger\Enum;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Canonical PSR-3 logging levels backed by their standardized string names.
 *
 * Configuration MUST use the exact lowercase names resolved by tryFrom().
 * The factory MAY also accept this enum directly at its public creation API.
 */
enum LogLevel: string
{
    /** Detailed diagnostic information. */
    case DEBUG = PsrLogLevel::DEBUG;
    /** Informational operational events. */
    case INFO = PsrLogLevel::INFO;
    /** Significant events that do not represent errors. */
    case NOTICE = PsrLogLevel::NOTICE;
    /** Exceptional circumstances that warrant attention. */
    case WARNING = PsrLogLevel::WARNING;
    /** Runtime errors affecting an operation. */
    case ERROR = PsrLogLevel::ERROR;
    /** Critical failures affecting application functionality. */
    case CRITICAL = PsrLogLevel::CRITICAL;
    /** Failures requiring immediate action. */
    case ALERT = PsrLogLevel::ALERT;
    /** Failures that render the system unusable. */
    case EMERGENCY = PsrLogLevel::EMERGENCY;
}
