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
use Omega\Console\Commands\MakeMigrationCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Tests\Console\Commands\make_migration_app;

covers(MakeMigrationCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_migration_app(): array
{
    $base = sys_get_temp_dir() . '/omegamig-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $dir = $base . '/migrations/';
    mkdir($dir, 0777, true);

    $app = new Application($base);
    $app->set('path.migrations', $dir);

    return [$app, $dir];
}

it('creates a migration file for make:migration', function (): void {
    [$app, $dir] = make_migration_app();

    $command = new MakeMigrationCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'CreateUsersTable']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Making migration...')
        ->and($result->getDisplay())->toContain('Success! Migration file created:')
        ->and(glob($dir . '/*_createuserstable.php'))->not->toBeEmpty();

    $app->flush();
});

it('uses the update stub for make:migration --update', function (): void {
    [$app, $dir] = make_migration_app();

    $command = new MakeMigrationCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'AddAgeToUsers', '--update' => true]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Success! Migration file created:')
        ->and(glob($dir . '/*_addagetousers.php'))->not->toBeEmpty();

    $app->flush();
});

it('requires a table name when the argument is empty', function (): void {
    [$app] = make_migration_app();

    expect(function () use ($app): mixed {
        $command = new MakeMigrationCommand();
        $command->app = $app;

        return (new CommandTester($command))->run(['name' => ''], ['']);
    })->toThrow(RuntimeException::class);

    $app->flush();
});

it('fails when the path.migrations binding is not a string', function (): void {
    [$app] = make_migration_app();
    $app->set('path.migrations', 123);

    $command = new MakeMigrationCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'CreateUsersTable']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.migrations" binding must resolve to a string path.');

    $app->flush();
});

it('fails when the migration file cannot be written', function (): void {
    $base = sys_get_temp_dir() . '/omegamig-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $blocker = $base . 'blocker';
    touch($blocker);
    $app->set('path.migrations', $blocker . '/migrations');

    $command = new MakeMigrationCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run(['name' => 'CreateUsersTable']);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Can't create migration file in:");

    $app->flush();
});