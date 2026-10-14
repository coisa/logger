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

/** An implementation whose constructor is not publicly accessible. */
final class PrivateConstructorProcessor implements ProcessorInterface
{
    /** Prevents construction from outside the fixture. */
    private function __construct() {}

    /** Preserves the synthetic record without side effects. */
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record;
    }
}
