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
use Omega\Console\Commands\MakeExceptionCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use const DIRECTORY_SEPARATOR;

use function Tests\Console\Commands\make_exception_file_app;

covers(MakeExceptionCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_exception_file_app(): array
{
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));

    $save = $base . '/save';
    mkdir($save, 0777, true);

    $app = new Application($base);
    $app->set('path.exception', $save);

    return [$app, $save];
}

it('creates an exception file for make:exception', function (): void {
    [$app, $save] = make_exception_file_app();

    $command = new MakeExceptionCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    $expected = 'Exception [app' . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR
        . 'UserProfileException.php] created successfully.';

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain($expected)
        ->and(file_exists($save . '/UserProfileException.php'))->toBeTrue();

    $content = (string) file_get_contents($save . '/UserProfileException.php');
    expect($content)->toContain('class UserProfileException extends ExceptionHandler')
        ->and($content)->toContain('namespace App\Exceptions;')
        ->and($content)->not->toContain('#!');

    $app->flush();
});

it('reports that the exception file already exists', function (): void {
    [$app, $save] = make_exception_file_app();
    file_put_contents($save . '/UserException.php', 'x');

    $command = new MakeExceptionCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('already exists.');

    $app->flush();
});

it('rejects a non string name argument', function (): void {
    [$app] = make_exception_file_app();

    $command = new MakeExceptionCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 42]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "name" argument must be a string.');

    $app->flush();
});

it('fails when the path.exception binding is not a string', function (): void {
    [$app] = make_exception_file_app();
    $app->set('path.exception', 123);

    $command = new MakeExceptionCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'User']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.exception" binding must resolve to a string path.');

    $app->flush();
});

it('fails when the target directory cannot be created', function (): void {
    $base = sys_get_temp_dir() . '/omegamkc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $blocker = $base . 'blocker';
    touch($blocker);
    $app->set('path.exception', $blocker);

    $command = new MakeExceptionCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run(['name' => 'User']);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Unable to create directory');

    $app->flush();
});