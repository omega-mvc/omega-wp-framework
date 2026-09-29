<?php

declare(strict_types=1);

/**
 * Part of Omega - Tests\Console\Commands\Fixtures.
 *
 * @link      https://omega-mvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Axel Magold (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

use Omega\Router\Router;

Router::get('/home', 'Home@index')->name('home');

Router::post('/users', static function (): string {
    return 'store';
})->middleware([\Omega\Middleware\ThrottleMiddleware::class]);

Router::match(['GET', 'POST'], '/contact', 'Contact@form');