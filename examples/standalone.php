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
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentor.dev.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

use CoiSA\Logger\LoggerFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$logger = LoggerFactory::createLogger();
$logger->info('Hello World!', ['key' => uniqid('key'), 'name' => uniqid('name')]);
$logger->info('Hello World!', ['timer' => ['test' => 'start'], 'key' => uniqid('key'), 'name' => uniqid('name')]);
sleep(1);
$logger->info('Hello World!', ['timer' => ['test2' => 'start'], 'key' => uniqid('key'), 'name' => uniqid('name')]);
sleep(2);
$logger->info('Hello World!', ['timer' => ['test' => 'start'], 'key' => uniqid('key'), 'name' => uniqid('name')]);
sleep(1);
$logger->info('Hello World!', ['key' => uniqid('key'), 'name' => uniqid('name')]);
$logger->info('Hello World!', ['timer' => ['test' => 'stop'], 'key' => uniqid('key'), 'name' => uniqid('name')]);
sleep(1);
$logger->info('Hello World!', ['timer' => ['test2' => 'stop'], 'key' => uniqid('key'), 'name' => uniqid('name')]);
$logger->info('Hello World!', ['timer' => ['test' => 'start'], 'key' => uniqid('key'), 'name' => uniqid('name')]);

return $logger;
