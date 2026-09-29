<?php

declare(strict_types=1);

/*
 * Part of Omega - Tests\Console\Commands Package.
 *
 * @link      https://omegamvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright 2025-2026 The Omega MVC Framework
 * @license   https://opensource.org/license/mit The MIT License
 * @version   2.0.0
 */

namespace Tests\Console\Commands;

use Omega\Application\Application;
use Omega\Console\Commands\RouteListCommand;
use Omega\Router\Router;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(RouteListCommand::class);

it('reports when there are no routes', function (): void {
    $base = sys_get_temp_dir() . '/omegarls-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    Router::reset();

    $command = new RouteListCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('No routes found.');

    Router::reset();
    $app->flush();
});

it('lists the registered routes', function (): void {
    $base = sys_get_temp_dir() . '/omegarls-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    Router::reset();
    Router::get('/home', static function (): string {
        return 'home';
    })->name('home');
    Router::delete('/posts/(:id)', static function (): string {
        return 'posts';
    });

    $command = new RouteListCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('GET|HEAD')
        ->and($display)->toContain('DELETE')
        ->and($display)->toContain('/home')
        ->and($display)->toContain('/posts/(:id)')
        ->and($display)->toContain('home')
        ->and($display)->toContain('Showing [2] routes.');

    Router::reset();
    $app->flush();
});