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
use Omega\Console\Commands\MaintenanceDownCommand;
use const DIRECTORY_SEPARATOR;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(MaintenanceDownCommand::class);

it('reports an error when the application is already under maintenance mode', function (): void {
    $base = sys_get_temp_dir() . '/omegamdd-' . bin2hex(random_bytes(4));
    mkdir($base . '/storage/app/maintenance.php', 0777, true);

    $app = new Application($base);

    $command = new MaintenanceDownCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Application is already under maintenance mode.');

    $app->flush();
});

it('puts the application into maintenance mode with default options', function (): void {
    $base = sys_get_temp_dir() . '/omegamdd-' . bin2hex(random_bytes(4));
    mkdir($base . '/storage/app', 0777, true);

    $app = new Application($base);

    $command = new MaintenanceDownCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    $downPath        = $base . '/storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'down';
    $maintenancePath = $base . '/storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'maintenance.php';
    $stub            = (string) file_get_contents(dirname(__DIR__, 4) . '/src/Omega/Console/stubs/maintenance.stub');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Application is now in maintenance mode.')
        ->and(file_exists($downPath))->toBeTrue()
        ->and(file_get_contents($downPath))->toContain('503')
        ->and(file_get_contents($downPath))->toContain('NULL')
        ->and(file_get_contents($maintenancePath))->toBe($stub);

    $app->flush();
});

it('compiles the provided redirect, retry, status and template options into the down file', function (): void {
    $base = sys_get_temp_dir() . '/omegamdd-' . bin2hex(random_bytes(4));
    mkdir($base . '/storage/app', 0777, true);

    $app = new Application($base);

    $command = new MaintenanceDownCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([
        '--redirect' => '/maintenance',
        '--retry'    => '120',
        '--status'   => '418',
        '--template' => '<h1>Under maintenance</h1>',
    ]);

    $down = (string) file_get_contents($base . '/storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'down');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($down)->toContain("'redirect' => '/maintenance',")
        ->and($down)->toContain("'retry'    => '120',")
        ->and($down)->toContain("'status'   => 418,")
        ->and($down)->toContain("'template' => '<h1>Under maintenance</h1>',");

    $app->flush();
});

it('rejects an invalid status code', function (): void {
    $base = sys_get_temp_dir() . '/omegamdd-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new MaintenanceDownCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--status' => '99']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The status code must be an integer between 100 and 599.');

    $app->flush();
});

it('rejects a negative retry value', function (): void {
    $base = sys_get_temp_dir() . '/omegamdd-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new MaintenanceDownCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--status' => '200', '--retry' => '-2']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The retry value must be an integer greater than or equal to zero.');

    $app->flush();
});

it('reports an error when the path.storage binding is not a string', function (): void {
    $base = sys_get_temp_dir() . '/omegamdd-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.storage', 123);

    $command = new MaintenanceDownCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.storage" binding must resolve to a string path.');

    $app->flush();
});

it('reports an error when the down configuration cannot be written', function (): void {
    $base = sys_get_temp_dir() . '/omegamdd-' . bin2hex(random_bytes(4));
    mkdir($base . '/w/app/down', 0777, true);

    $app = new Application($base);
    $app->set('path.storage', $base . '/w');

    $command = new MaintenanceDownCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Unable to write the maintenance mode configuration.');

    $app->flush();
});