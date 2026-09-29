<?php

declare(strict_types=1);

namespace Tests\Console;

use Omega\Application\Application;
use Omega\Console\ConsoleBranding;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

covers(ConsoleBranding::class);

it('skips the header for silent commands', function (): void {
    $app = new Application('/');
    $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $output = new BufferedOutput();

    $exit = $branding->doRun(new ArrayInput(['--version']), $output);

    expect($exit)->toBe(0)
        ->and($output->fetch())->not->toContain('Environment:');

    $app->flush();
});

it('renders the branded header for non-silent commands', function (): void {
    $app = new Application('/');
    $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
    $output = new BufferedOutput();

    $exit = $branding->doRun(new ArrayInput(['greet']), $output);
    $display = $output->fetch();

    expect($exit)->toBe(0)
        ->and($display)->toContain('____')
        ->and($display)->toContain('Environment:')
        ->and($display)->toContain('Debug: OFF')
        ->and($display)->toContain('Command Cache: NO');

    $app->flush();
});

it('renders the debug status when debug mode is enabled', function (): void {
    $app = new Application('/');
    $app->set('app.debug', true);
    $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
    $output = new BufferedOutput();

    $branding->doRun(new ArrayInput(['greet']), $output);

    expect($output->fetch())->toContain('Debug: ON');

    $app->flush();
});

it('renders the command cache status when the cache file exists', function (): void {
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omega-branding-' . bin2hex(random_bytes(4));
    $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'commands.php';
    mkdir($cacheDir, 0777, true);
    file_put_contents($cacheFile, '<?php return [];');

    try {
        $app = new Application($base);
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
        $output = new BufferedOutput();

        $branding->doRun(new ArrayInput(['greet']), $output);

        expect($output->fetch())->toContain('Command Cache: YES');
    } finally {
        @unlink($cacheFile);
        @rmdir($cacheDir);
        @rmdir(dirname($cacheDir));
        @rmdir($base);
    }
});

it('renders both the debug and command cache status as enabled', function (): void {
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omega-branding-' . bin2hex(random_bytes(4));
    $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'commands.php';
    mkdir($cacheDir, 0777, true);
    file_put_contents($cacheFile, '<?php return [];');

    try {
        $app = new Application($base);
        $app->set('app.debug', true);
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
        $output = new BufferedOutput();

        $branding->doRun(new ArrayInput(['greet']), $output);

        $display = $output->fetch();
        expect($display)->toContain('Debug: ON')
            ->and($display)->toContain('Command Cache: YES');
    } finally {
        @unlink($cacheFile);
        @rmdir($cacheDir);
        @rmdir(dirname($cacheDir));
        @rmdir($base);
    }
});

it('detects silent commands by their options', function (): void {
    $app = new Application('/');
    $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $isSilent = new ReflectionMethod(ConsoleBranding::class, 'isSilentCommand');

    foreach (['--version', '-V', '--quiet', '-q'] as $token) {
        expect($isSilent->invoke($branding, new ArrayInput([$token])))->toBeTrue();
    }

    $app->flush();
});

it('detects silent commands by their first argument', function (): void {
    $app = new Application('/');
    $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $isSilent = new ReflectionMethod(ConsoleBranding::class, 'isSilentCommand');

    foreach (['list', 'help', 'completion'] as $token) {
        expect($isSilent->invoke($branding, new ArrayInput([$token])))->toBeTrue();
    }

    expect($isSilent->invoke($branding, new ArrayInput(['serve'])))->toBeFalse()
        ->and($isSilent->invoke($branding, new ArrayInput([])))->toBeFalse();

    $app->flush();
});

it('formats byte counts in human readable units', function (): void {
    $app = new Application('/');
    $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $format = new ReflectionMethod(ConsoleBranding::class, 'formatBytes');

    expect($format->invoke($branding, 0))->toBe('0 B')
        ->and($format->invoke($branding, 500))->toBe('500 B')
        ->and($format->invoke($branding, 1024))->toBe('1 KB')
        ->and($format->invoke($branding, 1024 * 1024))->toBe('1 MB')
        ->and($format->invoke($branding, 1024 ** 3))->toBe('1 GB')
        ->and($format->invoke($branding, 1024 ** 4))->toBe('1024 GB');

    $app->flush();
});
