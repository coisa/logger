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
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\Logger;

use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory for the immutable LoggerConfig service.
 *
 * Providers SHALL register this factory separately from LoggerFactory.
 * Missing Fast Forward configuration MUST preserve the configuration defaults.
 */
final class LoggerConfigFactory
{
    /**
     * Creates validated logger configuration from an optional container service.
     *
     * @param ContainerInterface $container The container supplying optional ConfigInterface.
     *
     * @return LoggerConfig The immutable normalized configuration.
     *
     * @throws \InvalidArgumentException When the source service or its settings are invalid.
     */
    public function __invoke(ContainerInterface $container): LoggerConfig
    {
        return new LoggerConfig($this->resolveConfig($container));
    }

    /**
     * Resolves the source or supplies an empty configuration for default values.
     *
     * @param ContainerInterface $container The dependency injection container.
     *
     * @return ConfigInterface The configuration source for this value object's construction.
     *
     * @throws \InvalidArgumentException When the registered source does not implement ConfigInterface.
     */
    private function resolveConfig(ContainerInterface $container): ConfigInterface
    {
        if (!$container->has(ConfigInterface::class)) {
            return new ArrayConfig();
        }

        $config = $container->get(ConfigInterface::class);
        if (!$config instanceof ConfigInterface) {
            throw new \InvalidArgumentException(
                'The config service must implement ' . ConfigInterface::class . '.',
            );
        }

        return $config;
    }
}
