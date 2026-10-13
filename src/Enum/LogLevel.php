<?php

declare(strict_types=1);

namespace CoiSA\Logger\Enum;

use Psr\Log\LogLevel as PsrLogLevel;

enum LogLevel: string
{
    case DEBUG = PsrLogLevel::DEBUG;
    case INFO = PsrLogLevel::INFO;
    case NOTICE = PsrLogLevel::NOTICE;
    case WARNING = PsrLogLevel::WARNING;
    case ERROR = PsrLogLevel::ERROR;
    case CRITICAL = PsrLogLevel::CRITICAL;
    case ALERT = PsrLogLevel::ALERT;
    case EMERGENCY = PsrLogLevel::EMERGENCY;
}
