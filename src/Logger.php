<?php

declare(strict_types=1);

/**
 * This file is part of coisa/logger.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/factory
 * @link      https://12factor.net/logs
 *
 * @copyright Copyright (c) 2022 Felipe Sayão Lobato Abreu <github@mentor.dev.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MologLogger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Class Logger.
 *
 * @package CoiSA\Logger
 */
final class Logger extends AbstractLogger
{
    private LoggerInterface $logger;

    public function __construct(\DateTimeZone $timezone = null)
    {
        $handlers = [
            new StreamHandler('php://stdout'),
        ];

        $processors = [
            new PsrLogMessageProcessor(),
            new UidProcessor(),
            new ProcessIdProcessor(),
            new WebProcessor(),
            new MemoryUsageProcessor(),
            new MemoryPeakUsageProcessor(),
            new IntrospectionProcessor(MologLogger::DEBUG, [
                __NAMESPACE__,
                'Psr\\Log\\',
            ]),
        ];

        $this->logger = new MologLogger((string) gethostname(), $handlers, $processors, $timezone);
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
