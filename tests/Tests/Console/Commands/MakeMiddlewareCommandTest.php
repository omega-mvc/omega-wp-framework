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
use Omega\Console\Commands\MakeMiddlewareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use const DIRECTORY_SEPARATOR;

use function Tests\Console\Commands\make_middleware_file_app;

covers(MakeMiddlewareCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_middleware_file_app(): array
{
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));

    $save = $base . '/save';
    mkdir($save, 0777, true);

    $app = new Application($base);
    $app->set('path.middleware', $save);

    return [$app, $save];
}

it('creates a middleware file for make:middleware', function (): void {
    [$app, $save] = make_middleware_file_app();

    $command = new MakeMiddlewareCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    $expected = 'Middleware [app' . DIRECTORY_SEPARATOR . 'Middlewares' . DIRECTORY_SEPARATOR
        . 'UserProfileMiddleware.php] created successfully.';

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain($expected)
        ->and(file_exists($save . '/UserProfileMiddleware.php'))->toBeTrue();

    $content = (string) file_get_contents($save . '/UserProfileMiddleware.php');
    expect($content)->toContain('class UserProfileMiddleware')
        ->and($content)->toContain('namespace App\Middlewares;')
        ->and($content)->not->toContain('#!');

    $app->flush();
});

it('reports that the middleware file already exists', function (): void {
    [$app, $save] = make_middleware_file_app();
    file_put_contents($save . '/UserMiddleware.php', 'x');

    $command = new MakeMiddlewareCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('already exists.');

    $app->flush();
});

it('rejects a non string name argument', function (): void {
    [$app] = make_middleware_file_app();

    $command = new MakeMiddlewareCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 42]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "name" argument must be a string.');

    $app->flush();
});

it('fails when the path.middleware binding is not a string', function (): void {
    [$app] = make_middleware_file_app();
    $app->set('path.middleware', 123);

    $command = new MakeMiddlewareCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.middleware" binding must resolve to a string path.');

    $app->flush();
});

it('fails when the target directory cannot be created', function (): void {
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $blocker = $base . 'blocker';
    touch($blocker);
    $app->set('path.middleware', $blocker);

    $command = new MakeMiddlewareCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run(['name' => 'User']);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Unable to create directory');

    $app->flush();
});