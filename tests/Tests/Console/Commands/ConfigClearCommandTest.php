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
use Omega\Console\Commands\ConfigClearCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(ConfigClearCommand::class);

it('reports when there is no configuration cache file', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $command = new ConfigClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('No configuration cache file found.');

    $app->flush();
});

it('clears an existing configuration cache file', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'config.php';
    mkdir($app->getApplicationCachePath(), 0777, true);
    file_put_contents($cachePath, '<?php return [];');

    expect(file_exists($cachePath))->toBeTrue();

    $command = new ConfigClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Configuration cache cleared successfully.')
        ->and(file_exists($cachePath))->toBeFalse();

    $app->flush();
});

it('fails when the configuration cache file cannot be removed', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'config.php';
    mkdir(dirname($cachePath), 0777, true);
    mkdir($cachePath);

    $command = new ConfigClearCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Could not remove the configuration cache file.');

    $app->flush();
});