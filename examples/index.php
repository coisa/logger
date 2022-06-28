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

use CoiSA\Logger\LoggerFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function main(): void
{
    $logger = LoggerFactory::createLogger();
    $logger->info('Hello World!', ['name' => 'Felipe']);
    $logger->error('Buy World!', ['nickname' => 'CoiSA']);
    $logger->debug('Backtrace!', debug_backtrace());
}

main();
