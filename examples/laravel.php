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

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = new Laravel\Lumen\Application(dirname(__DIR__));

// Search for the following comments in the file `bootstrap/app.php`:

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/

// Add this line:
$app->register(CoiSA\Logger\LaravelServiceProvider::class);

return $app;
