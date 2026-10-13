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

/** Explicit processor identifiers; metadata-producing processors require informed selection. */
enum Processor: string
{
    /** Explicit psr message processing. */
    case PSR_MESSAGE = 'psr_message';
    /** Explicit sapi name processing. */
    case SAPI_NAME = 'sapi_name';
    /** Explicit process id processing. */
    case PROCESS_ID = 'process_id';
    /** Explicit uid processing. */
    case UID = 'uid';
    /** Explicit uuid processing. */
    case UUID = 'uuid';
    /** Explicit protocol version processing. */
    case PROTOCOL_VERSION = 'protocol_version';
    /** Explicit session id processing. */
    case SESSION_ID = 'session_id';
    /** Explicit request size processing. */
    case REQUEST_SIZE = 'request_size';
    /** Explicit client ip processing. */
    case CLIENT_IP = 'client_ip';
    /** Explicit is https processing. */
    case IS_HTTPS = 'is_https';
    /** Explicit web processing. */
    case WEB = 'web';
    /** Explicit backtrace processing. */
    case BACKTRACE = 'backtrace';
    /** Explicit memory usage processing. */
    case MEMORY_USAGE = 'memory_usage';
    /** Explicit memory peak usage processing. */
    case MEMORY_PEAK_USAGE = 'memory_peak_usage';
    /** Explicit load average processing. */
    case LOAD_AVERAGE = 'load_average';
    /** Explicit stopwatch timer processing. */
    case STOPWATCH_TIMER = 'stopwatch_timer';
}
