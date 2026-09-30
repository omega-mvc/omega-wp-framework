<?php

declare(strict_types=1);

namespace Tests\Console;

use Omega\Application\Application;
use Omega\Console\ConsoleApplication;
use Omega\Console\ConsoleBranding;
use Tests\Console\Fixtures\ConsoleApplicationHarness;
use Tests\Console\Fixtures\ConsoleSupport;
use Tests\Console\Fixtures\DemoCommand;
use Tests\Console\Fixtures\PlainCommand;

covers(ConsoleApplication::class);

it('returns an empty map when the cache file does not return an array', function (): void {
    $base = ConsoleSupport::newConsoleBase();
    $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
    mkdir($cacheDir, 0777, true);
    file_put_contents($cacheDir . DIRECTORY_SEPARATOR . 'commands.php', '<?php return \'not-an-array\';');

    try {
        $app = new Application('omega', $base);
        $console = new ConsoleApplicationHarness($app);
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

        $console->exposeConfigureCommandLoader($branding);

        expect($branding->has('demo:hello'))->toBeFalse();
    } finally {
        @unlink($cacheDir . DIRECTORY_SEPARATOR . 'commands.php');
        @rmdir($cacheDir);
        ConsoleSupport::removeConsoleBase($base);
    }
});

it('discovers commands from the framework and application paths', function (): void {
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php';
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryAliasedCommand.php';
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryNoAttributeCommand.php';
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryNotCommand.php';

    $base = ConsoleSupport::newConsoleBase();
    $appCommands = $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Commands';
    mkdir($appCommands, 0777, true);

    $fixtures = [
        'DiscoveryCommand.php',
        'DiscoveryAliasedCommand.php',
        'DiscoveryNoAttributeCommand.php',
        'DiscoveryNotCommand.php',
    ];

    foreach ($fixtures as $fixture) {
        copy(__DIR__ . '/Fixtures/DiscoveredCommands/' . $fixture, $appCommands . DIRECTORY_SEPARATOR . $fixture);
    }

    try {
        $app = new Application('omega', $base);
        $console = new ConsoleApplicationHarness($app);

        $map = $console->discoverCommands();

        expect($map['discover:one'])->toBe('App\Console\Commands\DiscoveryCommand')
            ->and($map['discover:two'])->toBe('App\Console\Commands\DiscoveryAliasedCommand')
            ->and($map['d2'])->toBe('App\Console\Commands\DiscoveryAliasedCommand')
            ->and($map['d3'])->toBe('App\Console\Commands\DiscoveryAliasedCommand')
            ->and($map)->not->toHaveKey('discover:no-attribute');
    } finally {
        foreach ($fixtures as $fixture) {
            @unlink($appCommands . DIRECTORY_SEPARATOR . $fixture);
        }
        @rmdir($appCommands);
        @rmdir(dirname($appCommands));
        ConsoleSupport::removeConsoleBase($base);
    }
});

it('skips applications paths that do not exist', function (): void {
    $base = ConsoleSupport::newConsoleBase();

    try {
        $app = new Application('omega', $base);
        $console = new ConsoleApplicationHarness($app);

        $map = $console->discoverCommands();

        expect($map)->not->toHaveKey('discover:one');
    } finally {
        ConsoleSupport::removeConsoleBase($base);
    }
});

it('attaches the command loader from a cache file', function (): void {
    $base = ConsoleSupport::newConsoleBase();
    $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
    mkdir($cacheDir, 0777, true);
    file_put_contents(
        $cacheDir . DIRECTORY_SEPARATOR . 'commands.php',
        '<?php return ' . var_export(['demo:hello' => DemoCommand::class], true) . ';'
    );

    try {
        $app = new Application('omega', $base);
        $console = new ConsoleApplicationHarness($app);
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

        $console->exposeConfigureCommandLoader($branding);

        expect($branding->has('demo:hello'))->toBeTrue();
    } finally {
        @unlink($cacheDir . DIRECTORY_SEPARATOR . 'commands.php');
        @rmdir($cacheDir);
        ConsoleSupport::removeConsoleBase($base);
    }
});

it('attaches the command loader from discovered commands', function (): void {
    require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php';

    $base = ConsoleSupport::newConsoleBase();
    $appCommands = $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Commands';
    mkdir($appCommands, 0777, true);
    copy(
        __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php',
        $appCommands . DIRECTORY_SEPARATOR . 'DiscoveryCommand.php',
    );

    try {
        $app = new Application('omega', $base);
        $console = new ConsoleApplicationHarness($app);
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

        $console->exposeConfigureCommandLoader($branding);

        expect($branding->has('discover:one'))->toBeTrue();
    } finally {
        @unlink($appCommands . DIRECTORY_SEPARATOR . 'DiscoveryCommand.php');
        @rmdir($appCommands);
        @rmdir(dirname($appCommands));
        ConsoleSupport::removeConsoleBase($base);
    }
});

it('executes console requests in a subprocess', function (): void {
    $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
    $base = ConsoleSupport::newConsoleBase();

    $scenarios = [
        [
            'input' => null,
            'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
            'shell' => '/bin/bash',
            'tokens' => ['pest', '--version'],
        ],
        [
            'input' => ['pest', '--version'],
            'output' => 'null',
            'shell' => '',
            'tokens' => [],
        ],
        [
            'input' => 'array-input',
            'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
            'shell' => '/usr/bin/nologin',
            'tokens' => ['pest', '--version'],
        ],
        [
            'input' => null,
            'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
            'shell' => '/usr/bin/zsh',
            'tokens' => ['pest', '--version'],
        ],
        [
            'input' => null,
            'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
            'shell' => '/usr/bin/fish',
            'tokens' => ['pest', '--version'],
        ],
    ];

    foreach ($scenarios as $scenario) {
        expect(ConsoleSupport::runHandleInSubprocess(
            $autoload,
            $base,
            $scenario['tokens'],
            $scenario['input'],
            $scenario['output'],
            $scenario['shell'],
        ))->toBe(0);
    }
});
