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
use Omega\Console\Commands\MakeControllerCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use const DIRECTORY_SEPARATOR;

use function Tests\Console\Commands\make_controller_file_app;

covers(MakeControllerCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_controller_file_app(): array
{
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));

    $save = $base . '/save';
    mkdir($save, 0777, true);

    $app = new Application($base);
    $app->set('path.controller', $save);

    return [$app, $save];
}

it('creates a controller file for make:controller', function (): void {
    [$app, $save] = make_controller_file_app();

    $command = new MakeControllerCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    $expected = 'Controller [app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR
        . 'Controllers' . DIRECTORY_SEPARATOR . 'UserProfileController.php] created successfully.';

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain($expected)
        ->and(file_exists($save . '/UserProfileController.php'))->toBeTrue();

    $app->flush();
});

it('replaces the kebab variable in the generated controller', function (): void {
    [$app, $save] = make_controller_file_app();

    $command = new MakeControllerCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserAdminExport']);

    $content = (string) file_get_contents($save . '/UserAdminExportController.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($content)->toContain('view(\'user-admin-export\'')
        ->and($content)->toContain('class UserAdminExportController extends AbstractController')
        ->and($content)->not->toContain('#!');

    $app->flush();
});

it('reports that the controller file already exists', function (): void {
    [$app, $save] = make_controller_file_app();
    file_put_contents($save . '/UserController.php', 'x');

    $command = new MakeControllerCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('already exists.');

    $app->flush();
});

it('rejects a non string name argument', function (): void {
    [$app] = make_controller_file_app();

    $command = new MakeControllerCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 42]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "name" argument must be a string.');

    $app->flush();
});

it('fails when the path.controller binding is not a string', function (): void {
    [$app] = make_controller_file_app();
    $app->set('path.controller', 123);

    $command = new MakeControllerCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.controller" binding must resolve to a string path.');

    $app->flush();
});

it('fails when the target directory cannot be created', function (): void {
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $blocker = $base . 'blocker';
    touch($blocker);
    $app->set('path.controller', $blocker);

    $command = new MakeControllerCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run(['name' => 'User']);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Unable to create directory');

    $app->flush();
});