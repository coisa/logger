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

use CoiSA\Logger\ConfigProvider;
use Laminas\ServiceManager\ServiceManager;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$serviceManager = new ServiceManager();
$configProvider = new ConfigProvider();

$serviceManager->configure($configProvider->getDependencies());

$logger = $serviceManager->get('logger');

$logger->info('Hello World!', ['key' => uniqid('key'), 'name' => uniqid('name')]);

return $logger;
