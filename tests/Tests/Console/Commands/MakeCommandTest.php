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
use Omega\Console\Commands\MakeCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use const DIRECTORY_SEPARATOR;

use function Tests\Console\Commands\make_command_file_app;

covers(MakeCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_command_file_app(): array
{
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));

    $save = $base . '/save';
    mkdir($save, 0777, true);

    $app = new Application($base);
    $app->set('path.command', $save);

    return [$app, $save];
}

it('creates a command file for make:command', function (): void {
    [$app, $save] = make_command_file_app();

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    $expected = 'Command [app' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR
        . 'Commands' . DIRECTORY_SEPARATOR . 'UserProfileCommand.php] created successfully.';

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain($expected)
        ->and(file_exists($save . '/UserProfileCommand.php'))->toBeTrue();

    $app->flush();
});

it('replaces the kebab variable in the generated command', function (): void {
    [$app, $save] = make_command_file_app();

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserAdminExport']);

    $content = (string) file_get_contents($save . '/UserAdminExportCommand.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($content)->toContain('name: \'app:user-admin-export\'')
        ->and($content)->toContain('class UserAdminExportCommand extends AbstractCommand')
        ->and($content)->not->toContain('#!');

    $app->flush();
});

it('reports that the command file already exists', function (): void {
    [$app, $save] = make_command_file_app();
    file_put_contents($save . '/UserCommand.php', 'x');

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('already exists.');

    $app->flush();
});

it('rejects a non string name argument', function (): void {
    [$app] = make_command_file_app();

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 42]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "name" argument must be a string.');

    $app->flush();
});

it('fails when the path.command binding is not a string', function (): void {
    [$app] = make_command_file_app();
    $app->set('path.command', 123);

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.command" binding must resolve to a string path.');

    $app->flush();
});

it('fails when the target directory cannot be created', function (): void {
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $blocker = $base . 'blocker';
    touch($blocker);
    $app->set('path.command', $blocker);

    $command = new MakeCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run(['name' => 'User']);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Unable to create directory');

    $app->flush();
});