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
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\Logger\Enum;

use CoiSA\Logger\StopwatchTimerProcessor;
use Monolog\Processor\LoadAverageProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
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

/** Allowlisted processor classes in the legacy default processing order. */
enum Processor: string
{
    /** Explicit psr message processing. */
    case PSR_MESSAGE = PsrLogMessageProcessor::class;

    /** Explicit sapi name processing. */
    case SAPI_NAME = SapiNameProcessor::class;

    /** Explicit process id processing. */
    case PROCESS_ID = ProcessIdProcessor::class;

    /** Explicit uid processing. */
    case UID = UidProcessor::class;

    /** Explicit uuid processing. */
    case UUID = UuidProcessor::class;

    /** Explicit protocol version processing. */
    case PROTOCOL_VERSION = ProtocolVersionProcessor::class;

    /** Explicit session id processing. */
    case SESSION_ID = SessionIdProcessor::class;

    /** Explicit request size processing. */
    case REQUEST_SIZE = RequestSizeProcessor::class;

    /** Explicit client ip processing. */
    case CLIENT_IP = ClientIpProcessor::class;

    /** Explicit is https processing. */
    case IS_HTTPS = IsHttpsProcessor::class;

    /** Explicit web processing. */
    case WEB = WebProcessor::class;

    /** Explicit backtrace processing. */
    case BACKTRACE = BacktraceProcessor::class;

    /** Explicit memory usage processing. */
    case MEMORY_USAGE = MemoryUsageProcessor::class;

    /** Explicit memory peak usage processing. */
    case MEMORY_PEAK_USAGE = MemoryPeakUsageProcessor::class;

    /** Explicit load average processing. */
    case LOAD_AVERAGE = LoadAverageProcessor::class;

    /** Explicit stopwatch timer processing. */
    case STOPWATCH_TIMER = StopwatchTimerProcessor::class;
}
