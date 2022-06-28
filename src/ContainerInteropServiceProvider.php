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

use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class ContainerInteropServiceProvider implements ServiceProviderInterface
{
    public function getFactories(): array
    {
        return [
            'logger'               => $this->createAliasCallableFactory(LoggerInterface::class),
            LoggerInterface::class => $this->createAliasCallableFactory(Logger::class),
            Logger::class          => new LoggerFactory(),
        ];
    }

    public function getExtensions(): array
    {
        return [];
    }

    private function createAliasCallableFactory(string $alias): callable
    {
        return fn (ContainerInterface $container): LoggerInterface => $container->get($alias);
    }
}
