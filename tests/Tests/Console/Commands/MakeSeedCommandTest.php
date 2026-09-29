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
use Omega\Console\Commands\MakeSeedCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Tests\Console\Commands\make_seeder_app;

covers(MakeSeedCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_seeder_app(): array
{
    $base = sys_get_temp_dir() . '/omegaseeder-' . bin2hex(random_bytes(4));

    $save = $base . '/seeders';
    mkdir($save, 0777, true);

    $app = new Application($base);
    $app->set('path.seeder', $save);

    return [$app, $save];
}

it('creates a seeder file for make:seeder', function (): void {
    [$app, $save] = make_seeder_app();

    $command = new MakeSeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserSeeder']);

    $content = (string) file_get_contents($save . '/UserSeeder.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Seeder [UserSeeder] created successfully.')
        ->and(file_exists($save . '/UserSeeder.php'))->toBeTrue()
        ->and($content)->toContain('namespace Database\Seeders;')
        ->and($content)->toContain('extends AbstractSeeder');

    $app->flush();
});

it('rejects a non string name argument for make:seeder', function (): void {
    [$app] = make_seeder_app();

    $command = new MakeSeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 42]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "name" argument must be a string.');

    $app->flush();
});

it('fails when the path.seeder binding is not a string', function (): void {
    [$app] = make_seeder_app();
    $app->set('path.seeder', 123);

    $command = new MakeSeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserSeeder']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.seeder" binding must resolve to a string path.');

    $app->flush();
});

it('reports that the seeder already exists without force', function (): void {
    [$app, $save] = make_seeder_app();
    file_put_contents($save . '/UserSeeder.php', 'x');

    $command = new MakeSeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserSeeder']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Seeder [UserSeeder] already exists!');

    $app->flush();
});

it('overwrites an existing seeder file with --force', function (): void {
    [$app, $save] = make_seeder_app();
    file_put_contents($save . '/UserSeeder.php', 'old');

    $command = new MakeSeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserSeeder', '--force' => true]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and((string) file_get_contents($save . '/UserSeeder.php'))->not->toContain('old');

    $app->flush();
});

it('fails when the seeder file cannot be written', function (): void {
    $base = sys_get_temp_dir() . '/omegaseeder-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $blocker = $base . 'blocker';
    touch($blocker);
    $app->set('path.seeder', $blocker . '/seeders');

    $command = new MakeSeedCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run(['name' => 'UserSeeder']);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Failed to create seeder [UserSeeder].');

    $app->flush();
});