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
use Omega\Console\Commands\RouteClearCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(RouteClearCommand::class);

it('reports when there is no route cache file', function (): void {
    $base = sys_get_temp_dir() . '/omegarl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new RouteClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('No route cache file found.');

    $app->flush();
});

it('clears the route cache file', function (): void {
    $base = sys_get_temp_dir() . '/omegarl-' . bin2hex(random_bytes(4));

    $app = new Application($base);

    mkdir($app->getApplicationCachePath(), 0777, true);
    file_put_contents($app->getApplicationCachePath() . 'route.php', '<?php return [];');

    $command = new RouteClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    $cachePath = $app->getApplicationCachePath() . 'route.php';

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Route cache cleared successfully.')
        ->and(file_exists($cachePath))->toBeFalse();

    $app->flush();
});

it('reports an error when the route cache file cannot be removed', function (): void {
    $base = sys_get_temp_dir() . '/omegarl-' . bin2hex(random_bytes(4));
    $cachePath = $base . '/bootstrap/cache/route.php';
    mkdir($cachePath, 0777, true);

    $app = new Application($base);

    $command = new RouteClearCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Unable to delete the route cache file.')
        ->and(file_exists($cachePath))->toBeTrue();

    $app->flush();
});