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
use Omega\Console\Commands\CommandMapCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(CommandMapCommand::class);

it('caches the discovered command map', function (): void {
    $base = sys_get_temp_dir() . '/omegacm-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'commands.php';
    mkdir($app->getApplicationCachePath(), 0777, true);

    $command = new CommandMapCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain("Command map successfully cached at: {$cachePath}")
        ->and(file_exists($cachePath))->toBeTrue();

    $content = (string) file_get_contents($cachePath);
    expect($content)->toContain('use Omega\Console\Commands\MakeCommand;')
        ->and($content)->toContain("'make:command' => MakeCommand::class,")
        ->and($content)->toContain('return [');

    $app->flush();
});

it('aborts when command short names collide', function (): void {
    $base = sys_get_temp_dir() . '/omegacm-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    require_once __DIR__ . '/Fixtures/MakeCommand.php';
    $app->set('path.command', __DIR__ . '/Fixtures');

    $command = new CommandMapCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("short-name collision on 'MakeCommand'");

    $app->flush();
});

it('fails when the cache file cannot be written', function (): void {
    $base = sys_get_temp_dir() . '/omegacm-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'commands.php';
    mkdir(dirname(dirname($cachePath)), 0777, true);
    touch(dirname($cachePath));

    $command = new CommandMapCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to write cache file at: {$cachePath}");

    $app->flush();
});