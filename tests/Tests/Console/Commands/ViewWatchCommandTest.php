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
use Omega\Console\Commands\ViewWatchCommand;
use Omega\View\Templator;
use ReflectionObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeTemplator;

use function file_put_contents;
use function mkdir;

covers(ViewWatchCommand::class);

it('reports an error when the path.view binding is not a string', function (): void {
    $base = sys_get_temp_dir() . '/omegavw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.view', 123);

    $command = new ViewWatchCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.view" binding must resolve to a string path.');

    $app->flush();
});

it('reports a failure when there are no view files to watch', function (): void {
    $base = sys_get_temp_dir() . '/omegavw-' . bin2hex(random_bytes(4));
    mkdir($base . '/views', 0777, true);

    $app = new Application($base);
    $app->set('path.view', $base . '/views');
    $app->set(Templator::class, new FakeTemplator());

    $command = new ViewWatchCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Watching view files in');

    $app->flush();
});

it('precompiles the indexed views and exits when the watch loop is disabled', function (): void {
    $base = sys_get_temp_dir() . '/omegavw-' . bin2hex(random_bytes(4));
    mkdir($base . '/views', 0777, true);
    file_put_contents($base . '/views/welcome.view.php', '<h1>Welcome</h1>');
    file_put_contents($base . '/views/page.view.php', '<p>Page</p>');

    $templator = new FakeTemplator();

    $app = new Application($base);
    $app->set('path.view', $base . '/views');
    $app->set(Templator::class, $templator);

    $command = new ViewWatchCommand();
    $command->app = $app;

    $reflection = new ReflectionObject($command);
    $shouldExit = $reflection->getProperty('shouldExit');
    $shouldExit->setValue($command, true);

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Watching view files in')
        ->and($result->getDisplay())->toContain('PRE-COMPILE')
        ->and($templator->compiled)->toHaveCount(2);

    $app->flush();
});