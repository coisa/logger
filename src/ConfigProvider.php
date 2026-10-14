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

use Psr\Log\LoggerInterface;

/**
 * Declares Laminas-compatible logger and configuration service registrations.
 */
final class ConfigProvider
{
    /**
     * Exports the dependency registry without resolving its factories.
     *
     * @return array{
     *     dependencies: array{
     *         aliases: array<string, class-string>,
     *         factories: array<class-string, class-string|callable>
     *     }
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * Describes the dedicated configuration factory alongside logger aliases.
     *
     * @return array{aliases: array<string, class-string>, factories: array<class-string, class-string|callable>}
     */
    public function getDependencies(): array
    {
        return [
            'aliases' => [
                'logger'               => LoggerInterface::class,
            ],
            'factories' => [
                LoggerInterface::class => LoggerFactory::class,
                Logger::class => [LoggerFactory::class, 'createConcrete'],
                LoggerConfig::class => LoggerConfigFactory::class,
            ],
        ];
    }
}
