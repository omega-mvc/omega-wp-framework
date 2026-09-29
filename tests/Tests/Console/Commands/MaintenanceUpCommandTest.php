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
use Omega\Console\Commands\MaintenanceUpCommand;
use const DIRECTORY_SEPARATOR;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(MaintenanceUpCommand::class);

it('reports an error when the application is already live', function (): void {
    $base = sys_get_temp_dir() . '/omegamdu-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new MaintenanceUpCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Application is already live.');

    $app->flush();
});

it('removes the maintenance files and brings the application back online', function (): void {
    $base = sys_get_temp_dir() . '/omegamdu-' . bin2hex(random_bytes(4));
    $appDir = $base . '/storage/app' . DIRECTORY_SEPARATOR;
    mkdir($appDir, 0777, true);
    touch($appDir . 'maintenance.php');
    touch($appDir . 'down');

    $app = new Application($base);

    $command = new MaintenanceUpCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Application is now live.')
        ->and(file_exists($appDir . 'maintenance.php'))->toBeFalse()
        ->and(file_exists($appDir . 'down'))->toBeFalse();

    $app->flush();
});

it('reports an error when a maintenance file cannot be removed', function (): void {
    $base = sys_get_temp_dir() . '/omegamdu-' . bin2hex(random_bytes(4));
    $appDir = $base . '/storage/app' . DIRECTORY_SEPARATOR;
    mkdir($appDir, 0777, true);
    mkdir($appDir . 'maintenance.php');

    $app = new Application($base);

    $command = new MaintenanceUpCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    $path = $appDir . 'maintenance.php';

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to remove the maintenance file '$path'.")
        ->and($result->getDisplay())->toContain("Please remove it manually at: $path")
        ->and(file_exists($path))->toBeTrue();

    $app->flush();
});