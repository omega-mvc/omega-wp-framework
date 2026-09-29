<?php

declare(strict_types=1);

namespace Tests\Console;

use Omega\Application\Application;
use Omega\Console\ConsoleApplication;
use Omega\Console\ConsoleBranding;
use Omega\Console\CommandLoader;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Tests\Console\Fixtures\ConsoleApplicationHarness;
use Tests\Console\Fixtures\DemoCommand;
use Tests\Console\Fixtures\PlainCommand;

covers(ConsoleApplication::class);

/**
 * @param array<int, string> $tokens
 */
function runHandleInSubprocess(string $autoload, string $base, array $tokens, mixed $input, string $outputCode, string $shell): int
{
    $inputCode = match (true) {
        $input === null => 'null',
        $input === 'array-input' => 'new \Symfony\Component\Console\Input\ArrayInput(' . var_export($tokens, true) . ')',
        default => var_export($input, true),
    };

    $autoloadCode = var_export($autoload, true);
    $baseCode = var_export($base, true);
    $argvCode = var_export($tokens, true);

    $code = "require {$autoloadCode}; \$_SERVER['argv'] = {$argvCode};"
        . " \$app = new \Omega\Application\Application({$baseCode});"
        . ' $app->bootstrapWith([]);'
        . ' $console = new \Omega\Console\ConsoleApplication($app);'
        . " \$output = {$outputCode};"
        . " \$exit = \$console->handle({$inputCode}, \$output);"
        . ' echo $exit;';

    $env = $shell === '' ? 'SHELL= ' : 'SHELL=' . escapeshellarg($shell) . ' ';
    $command = $env . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code);

    exec($command, $lines, $status);

    return $status;
}

function newConsoleBase(): string
{
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omega-console-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    return $base;
}

function removeConsoleBase(string $base): void
{
    foreach (['/bootstrap/cache', '/bootstrap', ''] as $suffix) {
        $path = $base . $suffix;
        if (is_dir($path)) {
            rmdir($path);
        }
    }
}

it('bootstraps the application when it has not been bootstrapped yet', function (): void {
    $app = new Application('/');
    $console = new ConsoleApplicationHarness($app);

    $console->exposeBootstrap();

    expect($app->bootstrapped)->toBeTrue();

    $app->flush();
});

it('skips bootstrapping when the application is already bootstrapped', function (): void {
    $app = new Application('/');
    $app->bootstrapWith([]);
    $console = new ConsoleApplicationHarness($app);

    $console->exposeBootstrap();

    expect($app->bootstrapped)->toBeTrue();

    $app->flush();
});

it('returns an empty map when the cache file does not return an array', function (): void {
    $base = newConsoleBase();
    $cacheFile = $base . DIRECTORY_SEPARATOR . 'map.php';
    file_put_contents($cacheFile, '<?php return \'not-an-array\';');

    try {
        $app = new Application($base);
        $console = new ConsoleApplicationHarness($app);

        expect($console->exposeLoadCachedCommands($cacheFile))->toBe([]);
    } finally {
        @unlink($cacheFile);
        removeConsoleBase($base);
    }
});

it('keeps only valid name-to-class entries from the cache file', function (): void {
    $base = newConsoleBase();
    $cacheFile = $base . DIRECTORY_SEPARATOR . 'map.php';
    file_put_contents(
        $cacheFile,
        '<?php return ' . var_export([
            'demo:hello' => DemoCommand::class,
            'missing:class' => 'No\\Such\\Class',
            'not:a-command' => stdClass::class,
            5 => DemoCommand::class,
            'plain:run' => PlainCommand::class,
        ], true) . ';'
    );

    try {
        $app = new Application($base);
        $console = new ConsoleApplicationHarness($app);

        expect($console->exposeLoadCachedCommands($cacheFile))->toBe([
            'demo:hello' => DemoCommand::class,
            'plain:run' => PlainCommand::class,
        ]);
    } finally {
        @unlink($cacheFile);
        removeConsoleBase($base);
    }
});

it('discovers commands from the framework and application paths', function (): void {
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php';
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryAliasedCommand.php';
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryNoAttributeCommand.php';
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryNotCommand.php';

    $base = newConsoleBase();
    $app = new Application($base);
    $app->set('path.command', __DIR__ . '/Fixtures/DiscoveredCommands');
    $console = new ConsoleApplicationHarness($app);

    $map = $console->discoverCommands();

    expect($map['discover:one'])->toBe('App\Console\Commands\DiscoveryCommand')
        ->and($map['discover:two'])->toBe('App\Console\Commands\DiscoveryAliasedCommand')
        ->and($map['d2'])->toBe('App\Console\Commands\DiscoveryAliasedCommand')
        ->and($map['d3'])->toBe('App\Console\Commands\DiscoveryAliasedCommand')
        ->and($map)->not->toHaveKey('discover:no-attribute');

    $app->flush();
    removeConsoleBase($base);
});

it('ignores the application path when it is not a string', function (): void {
    $base = newConsoleBase();
    $app = new Application($base);
    $app->set('path.command', 123);
    $console = new ConsoleApplicationHarness($app);

    $map = $console->discoverCommands();

    expect($map)->not->toHaveKey('discover:one');

    $app->flush();
    removeConsoleBase($base);
});

it('skips applications paths that do not exist', function (): void {
    $base = newConsoleBase();
    $app = new Application($base);
    $app->set('path.command', $base . DIRECTORY_SEPARATOR . 'missing-dir');
    $console = new ConsoleApplicationHarness($app);

    $map = $console->discoverCommands();

    expect($map)->not->toHaveKey('discover:one');

    $app->flush();
    removeConsoleBase($base);
});

it('attaches the command loader from a cache file', function (): void {
    $base = newConsoleBase();
    $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
    mkdir($cacheDir, 0777, true);
    file_put_contents(
        $cacheDir . DIRECTORY_SEPARATOR . 'commands.php',
        '<?php return ' . var_export(['demo:hello' => DemoCommand::class], true) . ';'
    );

    try {
        $app = new Application($base);
        $app->set(DemoCommand::class, static fn (): DemoCommand => new DemoCommand());
        $console = new ConsoleApplicationHarness($app);
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

        $console->exposeConfigureCommandLoader($branding);

        expect($branding->has('demo:hello'))->toBeTrue();
    } finally {
        @unlink($cacheDir . DIRECTORY_SEPARATOR . 'commands.php');
        @rmdir($cacheDir);
        removeConsoleBase($base);
    }
});

it('attaches the command loader from discovered commands', function (): void {
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php';

    $base = newConsoleBase();
    $app = new Application($base);
    $app->set('path.command', __DIR__ . '/Fixtures/DiscoveredCommands');
    $app->set('App\Console\Commands\DiscoveryCommand', static fn () => new \App\Console\Commands\DiscoveryCommand());
    $console = new ConsoleApplicationHarness($app);
    $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

    $console->exposeConfigureCommandLoader($branding);

    expect($branding->has('discover:one'))->toBeTrue();

    $app->flush();
    removeConsoleBase($base);
});

it('executes console requests in a subprocess', function (): void {
    $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
    $base = newConsoleBase();

    $scenarios = [
        ['input' => null, 'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()', 'shell' => '/bin/bash', 'tokens' => ['pest', '--version']],
        ['input' => ['pest', '--version'], 'output' => 'null', 'shell' => '', 'tokens' => []],
        ['input' => 'array-input', 'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()', 'shell' => '/usr/bin/nologin', 'tokens' => ['pest', '--version']],
        ['input' => null, 'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()', 'shell' => '/usr/bin/zsh', 'tokens' => ['pest', '--version']],
        ['input' => null, 'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()', 'shell' => '/usr/bin/fish', 'tokens' => ['pest', '--version']],
    ];

    foreach ($scenarios as $scenario) {
        expect(runHandleInSubprocess(
            $autoload,
            $base,
            $scenario['tokens'],
            $scenario['input'],
            $scenario['output'],
            $scenario['shell'],
        ))->toBe(0);
    }
});
