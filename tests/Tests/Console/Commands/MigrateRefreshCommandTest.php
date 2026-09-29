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
use Omega\Console\Commands\MigrateRefreshCommand;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\ConsoleHarness;
use Tests\Console\Commands\Fixtures\ScriptedConnection;

covers(MigrateRefreshCommand::class);

function migrationFixtureDir(string $base): string
{
    $dir = $base . '/database/migrations';
    mkdir($dir, 0777, true);

    copy(
        __DIR__ . '/Fixtures/migrations/create_users_table.php',
        $dir . '/create_users_table.php'
    );

    return $dir;
}

it('aborts when running in production without confirmation', function (): void {
    $base = sys_get_temp_dir() . '/omegarefresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new MigrateRefreshCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::INVALID);

    $app->flush();
});

it('reports when the migrate:reset command cannot be dispatched', function (): void {
    $base = sys_get_temp_dir() . '/omegarefresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new MigrateRefreshCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--force' => true]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Unable to execute command 'migrate:reset': The application instance is not available.");

    $app->flush();
});

it('rolls back and re-runs all migrations when forced', function (): void {
    $base = sys_get_temp_dir() . '/omegarefresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    ScriptedConnection::resetShared();
    migrationFixtureDir($base);

    $app = new Application($base);
    $app->set('database', new ScriptedConnection('omega_test'));

    AbstractFacade::setFacadeBase($app);

    [$application] = ConsoleHarness::make($app);

    $command = new MigrateRefreshCommand();
    $command->app = $app;
    $command->setApplication($application);

    $result = (new CommandTester($command))->run(['--force' => true]);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('Rolling back all migrations')
        ->and($display)->toContain('create_users_table')
        ->and($display)->toContain('DONE');

    $app->flush();
});

it('prints the migration SQL without executing when refreshing in dry-run mode', function (): void {
    $base = sys_get_temp_dir() . '/omegarefresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    ScriptedConnection::resetShared();
    migrationFixtureDir($base);

    $app = new Application($base);
    $app->set('database', new ScriptedConnection('omega_test'));

    AbstractFacade::setFacadeBase($app);

    [$application] = ConsoleHarness::make($app);

    $command = new MigrateRefreshCommand();
    $command->app = $app;
    $command->setApplication($application);

    $result = (new CommandTester($command))->run(['--force' => true, '--dry-run' => true]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('CREATE TABLE users (id INT)');

    $app->flush();
});