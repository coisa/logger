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

/** An implementation requiring application-owned constructor arguments. */
final class RequiredConstructorProcessor implements ProcessorInterface
{
    /** @var int Number of records handled by this exact instance. */
    public int $calls = 0;

    /** Requires a value that the logger factory cannot provide. */
    public function __construct(public readonly string $label) {}

    /** Enriches the synthetic record and records use of this exact instance. */
    public function __invoke(LogRecord $record): LogRecord
    {
        ++$this->calls;

        return $record->with(extra: $record->extra + ['configured' => $this->label]);
    }
}
