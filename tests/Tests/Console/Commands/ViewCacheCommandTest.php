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
use Omega\Console\Commands\ViewCacheCommand;
use Omega\View\Templator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeTemplator;

use function file_put_contents;
use function mkdir;

covers(ViewCacheCommand::class);

it('reports an error when the path.view binding is not a string', function (): void {
    $base = sys_get_temp_dir() . '/omegavc-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.view', 123);

    $command = new ViewCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.view" binding must resolve to a string path.');

    $app->flush();
});

it('reports when there are no view files', function (): void {
    $base = sys_get_temp_dir() . '/omegavc-' . bin2hex(random_bytes(4));
    $viewPath = $base . '/views';
    mkdir($viewPath, 0777, true);

    $app = new Application($base);
    $app->set('path.view', $viewPath);

    $command = new ViewCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('No view files found.');

    $app->flush();
});

it('compiles every found view file', function (): void {
    $base = sys_get_temp_dir() . '/omegavc-' . bin2hex(random_bytes(4));
    mkdir($base . '/views', 0777, true);
    file_put_contents($base . '/views/welcome.view.php', '<h1>Welcome</h1>');
    file_put_contents($base . '/views/page.view.php', '<p>Page</p>');

    $templator = new FakeTemplator();

    $app = new Application($base);
    $app->set('path.view', $base . '/views');
    $app->set(Templator::class, $templator);

    $command = new ViewCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('View cache built successfully.')
        ->and($templator->compiled)->toHaveCount(2);

    $app->flush();
});

it('reports an error when a view file cannot be compiled', function (): void {
    $base = sys_get_temp_dir() . '/omegavc-' . bin2hex(random_bytes(4));
    mkdir($base . '/views', 0777, true);
    file_put_contents($base . '/views/broken.view.php', 'broken');

    $templator = new FakeTemplator();
    $templator->throw = new RuntimeException('Syntax error');

    $app = new Application($base);
    $app->set('path.view', $base . '/views');
    $app->set(Templator::class, $templator);

    $command = new ViewCacheCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Error compiling /broken.view.php: Syntax error');

    $app->flush();
});