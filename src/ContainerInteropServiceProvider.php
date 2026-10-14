<?php

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

declare(strict_types=1);

namespace CoiSA\Logger;

use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Portable registrations for logger configuration, logger creation and aliases.
 */
final class ContainerInteropServiceProvider implements ServiceProviderInterface
{
    /**
     * Describes lazy service factories without resolving container entries.
     *
     * @return array<string, callable> Entry identifiers and their factory callables.
     */
    public function getFactories(): array
    {
        return [
            'logger'               => $this->createAliasCallableFactory(LoggerInterface::class),
            LoggerInterface::class => $this->createAliasCallableFactory(Logger::class),
            Logger::class          => new LoggerFactory(),
            LoggerConfig::class    => new LoggerConfigFactory(),
        ];
    }

    /**
     * Declares that this provider does not decorate existing services.
     *
     * @return array<string, callable> The empty extension map.
     */
    public function getExtensions(): array
    {
        return [];
    }

    /**
     * Creates a lazy alias that preserves the resolved logger identity.
     *
     * @param string $alias The target logger entry identifier.
     *
     * @return callable(ContainerInterface): LoggerInterface The alias factory.
     */
    private function createAliasCallableFactory(string $alias): callable
    {
        return static fn (ContainerInterface $container): LoggerInterface => $container->get($alias);
    }
}
