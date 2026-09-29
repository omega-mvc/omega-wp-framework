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
use Omega\Application\ApplicationManifest;
use Omega\Console\Commands\PackageDiscoverCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeApplicationManifest;

covers(PackageDiscoverCommand::class);

it('reports when no discoverable packages are found', function (): void {
    $base = sys_get_temp_dir() . '/omegapd-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $manifest = new FakeApplicationManifest();
    $manifest->data = [];
    $app->set(ApplicationManifest::class, $manifest);

    $command = new PackageDiscoverCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Discovery packages in composer...')
        ->and($result->getDisplay())->toContain('No discoverable packages found.');

    $app->flush();
});

it('lists the discovered packages and reports success', function (): void {
    $base = sys_get_temp_dir() . '/omegapd-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $manifest = new FakeApplicationManifest();
    $manifest->data = [
        'vendor/package-a' => ['providers' => ['A\\Provider']],
        'vendor/package-b' => ['providers' => ['B\\Provider']],
    ];
    $app->set(ApplicationManifest::class, $manifest);

    $command = new PackageDiscoverCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('vendor/package-a')
        ->and($result->getDisplay())->toContain('vendor/package-b')
        ->and($result->getDisplay())->toContain('DONE')
        ->and($result->getDisplay())->toContain('Package manifest generated successfully.');

    $app->flush();
});

it('reports an error when the package discovery fails', function (): void {
    $base = sys_get_temp_dir() . '/omegapd-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $manifest = new FakeApplicationManifest();
    $manifest->throw = new RuntimeException('Packages could not be read');
    $app->set(ApplicationManifest::class, $manifest);

    $command = new PackageDiscoverCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Packages could not be read')
        ->and($result->getDisplay())->toContain("Can't create package manifest cache file.");

    $app->flush();
});