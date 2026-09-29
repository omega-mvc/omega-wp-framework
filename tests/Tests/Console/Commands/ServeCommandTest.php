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
use Omega\Console\Commands\ServeCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function fclose;
use function random_int;
use function stream_socket_get_name;
use function stream_socket_server;
use function substr;
use function strrchr;

covers(ServeCommand::class);

it('reports an error when the port is not numeric', function (): void {
    $base = sys_get_temp_dir() . '/omegas-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new ServeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--port' => 'abc']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The port must be an integer between 1 and 65535.');

    $app->flush();
});

it('reports an error when the port is below the valid range', function (): void {
    $base = sys_get_temp_dir() . '/omegas-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new ServeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--port' => '0']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The port must be an integer between 1 and 65535.');

    $app->flush();
});

it('reports an error when the port exceeds the valid range', function (): void {
    $base = sys_get_temp_dir() . '/omegas-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new ServeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--port' => '70000']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The port must be an integer between 1 and 65535.');

    $app->flush();
});

it('reports an error when the port is already in use', function (): void {
    $base = sys_get_temp_dir() . '/omegas-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $server = stream_socket_server('tcp://127.0.0.1:0');

    if ($server === false) {
        $this->fail('Could not create a local socket server for the port-in-use test.');
    }

    $name = stream_socket_get_name($server, false);

    if ($name === false) {
        $this->fail('Could not resolve a local socket port for the port-in-use test.');
    }

    $port = (int) substr(strrchr($name, ':') ?: '', 1);

    $command = new ServeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--port' => (string) $port]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("The port {$port} is already in use.");

    fclose($server);
    $app->flush();
});

it('prints the server banner before resolving the public path', function (): void {
    $base = sys_get_temp_dir() . '/omegas-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.public', 123);

    $port = random_int(20000, 50000);

    $command = new ServeCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    try {
        $result = (new CommandTester($command))->run(['--port' => (string) $port]);
    } finally {
        restore_error_handler();
    }

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('The "path.public" binding must resolve to a string path.')
        ->and($display)->toContain('Server running at:')
        ->and($display)->toContain('Local:')
        ->and($display)->toContain("http://127.0.0.1:{$port}")
        ->and($display)->toContain('Server running...');

    $app->flush();
});

it('prints the network address when the server is exposed', function (): void {
    $base = sys_get_temp_dir() . '/omegas-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.public', 123);

    $port = random_int(20000, 50000);

    $command = new ServeCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    try {
        $result = (new CommandTester($command))->run(['--port' => (string) $port, '--expose' => true]);
    } finally {
        restore_error_handler();
    }

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('Network:')
        ->and($display)->toContain("http://127.0.0.1:{$port}");

    $app->flush();
});