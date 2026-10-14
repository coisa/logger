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

namespace CoiSA\Logger;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Registers shared logger configuration and logger services in a Laravel application.
 */
final class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->alias(LoggerInterface::class, 'logger');
        $this->app->alias(Logger::class, LoggerInterface::class);
        $this->app->singleton(Logger::class, (new LoggerFactory())(...));
        $this->app->singleton(LoggerConfig::class, (new LoggerConfigFactory())(...));
    }
}
