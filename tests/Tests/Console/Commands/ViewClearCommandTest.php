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
use Omega\Console\Commands\ViewClearCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function chmod;
use function file_exists;
use function file_put_contents;
use function mkdir;

covers(ViewClearCommand::class);

it('reports an error when the path.compiled_view_path binding is not a string', function (): void {
    $base = sys_get_temp_dir() . '/omegavclr-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.compiled_view_path', 123);

    $command = new ViewClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.compiled_view_path" binding must resolve to a string path.');

    $app->flush();
});

it('reports a count of zero when there are no cached files', function (): void {
    $base = sys_get_temp_dir() . '/omegavclr-' . bin2hex(random_bytes(4));
    $compiled = $base . '/compiled';
    mkdir($compiled, 0777, true);

    $app = new Application($base);
    $app->set('path.compiled_view_path', $compiled);

    $command = new ViewClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Cleared 0 cached files.');

    $app->flush();
});

it('clears every cached file recursively', function (): void {
    $base = sys_get_temp_dir() . '/omegavclr-' . bin2hex(random_bytes(4));
    $compiled = $base . '/compiled';
    mkdir($compiled . '/nested', 0777, true);
    file_put_contents($compiled . '/a.php', 'x');
    file_put_contents($compiled . '/b.php', 'y');
    file_put_contents($compiled . '/nested/c.php', 'z');

    $app = new Application($base);
    $app->set('path.compiled_view_path', $compiled);

    $command = new ViewClearCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Cleared 3 cached files.')
        ->and(file_exists($compiled . '/a.php'))->toBeFalse()
        ->and(file_exists($compiled . '/b.php'))->toBeFalse()
        ->and(file_exists($compiled . '/nested/c.php'))->toBeFalse();

    $app->flush();
});

it('does not count files that cannot be removed', function (): void {
    $base = sys_get_temp_dir() . '/omegavclr-' . bin2hex(random_bytes(4));
    $compiled = $base . '/compiled';
    mkdir($compiled, 0777, true);
    file_put_contents($compiled . '/locked.php', 'x');

    $probe = $compiled . '/probe.php';
    file_put_contents($probe, 'y');
    @chmod($compiled, 0o555);

    set_error_handler(static fn (int $severity, string $message): bool => true);

    try {
        $probeRemoved = @unlink($probe);
    } finally {
        restore_error_handler();
    }

    if ($probeRemoved) {
        @chmod($compiled, 0o777);
        $this->markTestSkipped('Running with elevated privileges.');
    }

    $app = new Application($base);
    $app->set('path.compiled_view_path', $compiled);

    $command = new ViewClearCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    try {
        $result = (new CommandTester($command))->run([]);
    } finally {
        restore_error_handler();
    }

    @chmod($compiled, 0o777);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Cleared 0 cached files.')
        ->and(file_exists($compiled . '/locked.php'))->toBeTrue();

    $app->flush();
});