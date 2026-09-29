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
use Omega\Console\Commands\CommandClearCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(CommandClearCommand::class);

it('reports when there is no console cache file', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $command = new CommandClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('No console cache file found. Nothing to clear.');

    $app->flush();
});

it('clears an existing console cache file', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'commands.php';
    mkdir($app->getApplicationCachePath(), 0777, true);
    file_put_contents($cachePath, '<?php return [];');

    expect(file_exists($cachePath))->toBeTrue();

    $command = new CommandClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Console command cache cleared successfully.')
        ->and($result->getDisplay())->toContain('The application will now use dynamic discovery.')
        ->and(file_exists($cachePath))->toBeFalse();

    $app->flush();
});

it('fails when the console cache file cannot be deleted', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'commands.php';
    mkdir(dirname($cachePath), 0777, true);
    mkdir($cachePath);

    $command = new CommandClearCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to delete the cache file at: {$cachePath}");

    $app->flush();
});