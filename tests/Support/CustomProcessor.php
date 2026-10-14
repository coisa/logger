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
