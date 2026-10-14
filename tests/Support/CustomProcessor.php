<?php

declare(strict_types=1);

namespace CoiSA\Logger\Support;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Observable custom extension used without filesystem or application services. */
final class CustomProcessor implements ProcessorInterface
{
    /** @var int Number of instances constructed during the current test. */
    public static int $instances = 0;

    /** @var int Number of records processed during the current test. */
    public static int $calls = 0;

    /** Records construction so disabled logging can prove it creates no processors. */
    public function __construct()
    {
        ++self::$instances;
    }

    /** Adds a deterministic marker to a synthetic record. */
    public function __invoke(LogRecord $record): LogRecord
    {
        ++self::$calls;
        return $record->with(extra: $record->extra + ['custom' => 'synthetic']);
    }
}
