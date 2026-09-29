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
use Omega\Console\Commands\AbstractMakeCommand;
use Omega\Console\Commands\MakeCommand;
use Omega\Console\Commands\MakeControllerCommand;
use Omega\Console\Commands\MakeExceptionCommand;
use Omega\Console\Commands\MakeMiddlewareCommand;
use Omega\Console\Commands\MakeProviderCommand;
use Omega\Console\Commands\MakeViewCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\NoMakeCommand;

use function Tests\Console\Commands\make_command_app;

require_once __DIR__ . '/Fixtures/NoMakeCommand.php';

covers(AbstractMakeCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_command_app(string $binding = 'path.command'): array
{
    $base = sys_get_temp_dir() . '/omegacmd-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $save = $base . '/save';
    mkdir($save, 0777, true);

    $app = new Application($base);
    $app->set($binding, $save);

    return [$app, $save];
}

it('creates a command file for make:command', function (): void {
    [$app, $save] = make_command_app();

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Command')
        ->and($result->getDisplay())->toContain('created successfully')
        ->and(file_exists($save . '/UserProfileCommand.php'))->toBeTrue();

    $app->flush();
});

it('creates a controller file for make:controller', function (): void {
    [$app, $save] = make_command_app('path.controller');

    $command = new MakeControllerCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and(file_exists($save . '/UserProfileController.php'))->toBeTrue();

    $app->flush();
});

it('creates an exception file for make:exception', function (): void {
    [$app, $save] = make_command_app('path.exception');

    $command = new MakeExceptionCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and(file_exists($save . '/UserProfileException.php'))->toBeTrue();

    $app->flush();
});

it('creates a middleware file for make:middleware', function (): void {
    [$app, $save] = make_command_app('path.middleware');

    $command = new MakeMiddlewareCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and(file_exists($save . '/UserProfileMiddleware.php'))->toBeTrue();

    $app->flush();
});

it('creates a provider file for make:provider', function (): void {
    [$app, $save] = make_command_app('path.provider');

    $command = new MakeProviderCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('ServiceProvider')
        ->and(file_exists($save . '/UserProfileProvider.php'))->toBeTrue();

    $app->flush();
});

it('creates a view file for make:view', function (): void {
    [$app, $save] = make_command_app('path.view');

    $command = new MakeViewCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('view')
        ->and(file_exists($save . '/UserProfile.template.php'))->toBeTrue();

    $app->flush();
});

it('reports that the file already exists for make:command', function (): void {
    [$app, $save] = make_command_app();
    file_put_contents($save . '/UserCommand.php', 'x');

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('already exists');

    $app->flush();
});

it('rejects a non string name argument', function (): void {
    [$app] = make_command_app();

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 42]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "name" argument must be a string.');

    $app->flush();
});

it('fails when the Make attribute is missing', function (): void {
    [$app] = make_command_app();

    $command = new NoMakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Missing #[Make] attribute.');

    $app->flush();
});

it('fails when the path binding is not a string', function (): void {
    [$app] = make_command_app();
    $app->set('path.command', 123);

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.command" binding must resolve to a string path.');

    $app->flush();
});

it('fails when the target directory cannot be created', function (): void {
    $base = sys_get_temp_dir() . '/omegacmd-' . bin2hex(random_bytes(4));
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

it('replaces kebab pattern variables', function (): void {
    [$app, $save] = make_command_app();

    $command = new MakeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserAdminExport']);

    expect($result->statusCode)->toBe(Command::SUCCESS);

    $content = (string) file_get_contents($save . '/UserAdminExportCommand.php');
    expect($content)->toContain('user-admin-export');

    $app->flush();
});