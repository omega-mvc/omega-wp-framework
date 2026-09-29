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
use Omega\Console\Commands\ConfigCacheCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(ConfigCacheCommand::class);

it('caches the configuration', function (): void {
    $base = sys_get_temp_dir() . '/omegacc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'config.php';
    mkdir($app->getApplicationCachePath(), 0777, true);

    $command = new ConfigCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Configuration cached successfully.')
        ->and(file_exists($cachePath))->toBeTrue();

    $content = (string) file_get_contents($cachePath);
    expect($content)->toContain('<?php return array (');

    $app->flush();
});

it('fails when the configuration cache file cannot be written', function (): void {
    $base = sys_get_temp_dir() . '/omegacc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'config.php';
    mkdir(dirname(dirname($cachePath)), 0777, true);
    touch(dirname($cachePath));

    $command = new ConfigCacheCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Failed to write the configuration cache file.');

    $app->flush();
});

it('fails when an invalid cached configuration is present', function (): void {
    $base = sys_get_temp_dir() . '/omegacc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $cachePath = $app->getApplicationCachePath() . 'config.php';
    mkdir($app->getApplicationCachePath(), 0777, true);
    file_put_contents($cachePath, '<?php return "nope";');

    $command = new ConfigCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('An error occurred while caching configuration:')
        ->and($result->getDisplay())->toContain('Invalid config cache file: expected array, got string');

    $app->flush();
});