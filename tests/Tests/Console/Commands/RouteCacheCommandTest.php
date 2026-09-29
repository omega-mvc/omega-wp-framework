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
use Omega\Console\Commands\RouteCacheCommand;
use Omega\Router\Router;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(RouteCacheCommand::class);

it('reports when there are no routes to cache', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    Router::reset();

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('No routes to cache.');

    Router::reset();
    $app->flush();
});

it('creates the route cache file for closure and callable routes', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base . '/bootstrap/cache', 0777, true);

    $app = new Application($base);

    Router::reset();
    Router::get('/home', static function (): string {
        return 'home';
    });
    Router::post('/users', 'HomeController@store');

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    $cachePath = $app->getApplicationCachePath() . 'route.php';

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Route cache file has been successfully created.')
        ->and(file_exists($cachePath))->toBeTrue();

    $content = (string) file_get_contents($cachePath);

    expect($content)->toContain("'uri' => '/home'")
        ->and($content)->toContain("'uri' => '/users'")
        ->and($content)->toContain('HomeController@store');

    Router::reset();
    $app->flush();
});

it('requires the given route files before caching', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base . '/bootstrap/cache', 0777, true);
    file_put_contents(
        $base . '/custom-routes.php',
        '<?php \Omega\Router\Router::get(\'/api\', static function (): void {});'
    );

    $app = new Application($base);

    Router::reset();

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--files' => ['custom-routes.php']]);

    $content = (string) file_get_contents($app->getApplicationCachePath() . 'route.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Route cache file has been successfully created.')
        ->and($content)->toContain("'uri' => '/api'");

    Router::reset();
    $app->flush();
});

it('requires each route file only once', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base . '/bootstrap/cache', 0777, true);
    file_put_contents(
        $base . '/custom-routes.php',
        '<?php \Omega\Router\Router::get(\'/api\', static function (): void {});'
    );

    $app = new Application($base);

    Router::reset();

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([
        '--files' => ['custom-routes.php', 'custom-routes.php'],
    ]);

    $content = (string) file_get_contents($app->getApplicationCachePath() . 'route.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($content)->toContain("'uri' => '/api'");

    Router::reset();
    $app->flush();
});

it('reports an error when a given route file does not exist', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    Router::reset();

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--files' => ['missing-routes.php']]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Route file can't be loaded: 'missing-routes.php'");

    Router::reset();
    $app->flush();
});

it('reports an error when a given route file throws', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);
    file_put_contents($base . '/boom.php', '<?php throw new RuntimeException(\'Boom\');');

    $app = new Application($base);

    Router::reset();

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--files' => ['boom.php']]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Failed to load route file: Boom');

    Router::reset();
    $app->flush();
});

it('reports an error when the path.base binding is not a string', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.base', 123);

    Router::reset();

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--files' => ['routes.php']]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.base" binding must resolve to a string path.');

    Router::reset();
    $app->flush();
});

it('continues when a given file entry is not a string', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    Router::reset();

    $command = new RouteCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--files' => [123]]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('No routes to cache.');

    Router::reset();
    $app->flush();
});

it('reports an error when the route cache file cannot be written', function (): void {
    $base = sys_get_temp_dir() . '/omegarc-' . bin2hex(random_bytes(4));
    mkdir($base . '/bootstrap', 0777, true);
    touch($base . '/bootstrap/cache');

    $app = new Application($base);

    Router::reset();
    Router::get('/home', static function (): string {
        return 'home';
    });

    $command = new RouteCacheCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run([]);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Failed to build route cache.');

    Router::reset();
    $app->flush();
});