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

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->alias('logger', LoggerInterface::class);
        $this->app->alias(LoggerInterface::class, Logger::class);
        $this->app->singleton(Logger::class, new LoggerFactory());
    }
}
