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
use Omega\Cache\CacheManager;
use Omega\Console\Commands\CacheClearCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeCache;

covers(CacheClearCommand::class);

/**
 * @return array{0: Application, 1: CacheManager}
 */
function cache_clear_app(): array
{
    $base = sys_get_temp_dir() . '/omegacc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $cache = new CacheManager('file', new FakeCache());
    $app->set('cache', $cache);

    return [$app, $cache];
}

it('fails when the cache manager is not bound', function (): void {
    $base = sys_get_temp_dir() . '/omegacc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Cache is not set yet.');

    $app->flush();
});

it('clears the default cache driver', function (): void {
    [$app, $cache] = cache_clear_app();

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain("Cleared 'file' driver.");

    $app->flush();
});

it('fails when the default driver cannot be cleared', function (): void {
    [$app, $cache] = cache_clear_app();
    $app->set('cache', new CacheManager('file', new FakeCache()->setClearResult(false)));

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to clear 'file' driver.");

    $app->flush();
});

it('fails when the default driver throws', function (): void {
    [$app, $cache] = cache_clear_app();
    $app->set('cache', new CacheManager('file', new FakeCache()->setClearException(new RuntimeException('boom'))));

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to clear 'file' driver: boom");

    $app->flush();
});

it('clears all registered drivers with --all', function (): void {
    [$app, $cache] = cache_clear_app();
    $cache->setDriver('redis', new FakeCache());

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--all' => true]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain("Cleared 'file' driver.")
        ->and($result->getDisplay())->toContain("Cleared 'redis' driver.");

    $app->flush();
});

it('warns when --all overrides --drivers', function (): void {
    [$app, $cache] = cache_clear_app();
    $cache->setDriver('redis', new FakeCache());

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--all' => true, '--drivers' => ['file']]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain("'--all' overrides '--drivers': clearing all registered drivers.")
        ->and($result->getDisplay())->toContain("Cleared 'redis' driver.");

    $app->flush();
});

it('skips unsupported drivers when clearing specific drivers', function (): void {
    [$app, $cache] = cache_clear_app();
    $cache->setDriver('redis', new FakeCache()->setSupported(false));

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--drivers' => ['file', 'redis']]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain("Cleared 'file' driver.")
        ->and($result->getDisplay())->toContain("Skipping 'redis' driver: not supported.");

    $app->flush();
});

it('fails when a specific driver cannot be cleared', function (): void {
    [$app, $cache] = cache_clear_app();
    $cache->setDriver('redis', new FakeCache()->setClearResult(false));

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--drivers' => ['redis']]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to clear 'redis' driver.");

    $app->flush();
});

it('fails when a specific driver throws', function (): void {
    [$app, $cache] = cache_clear_app();
    $cache->setDriver('redis', new FakeCache()->setClearException(new RuntimeException('boom')));

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--drivers' => ['redis']]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to clear 'redis': boom");

    $app->flush();
});

it('fails for an unknown driver name', function (): void {
    [$app, $cache] = cache_clear_app();

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--drivers' => ['nope']]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Failed to clear 'nope':");

    $app->flush();
});

it('falls back to the default driver when only empty names are given', function (): void {
    [$app, $cache] = cache_clear_app();

    $command = new CacheClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--drivers' => ['']]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain("Cleared 'file' driver.");

    $app->flush();
});