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
use Omega\Console\Commands\SeedCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeSeeder;

covers(SeedCommand::class);

it('aborts the seeder when the production question is declined', function (): void {
    $base = sys_get_temp_dir() . '/omegaseed-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new SeedCommand();
    $command->app = $app;
    $command->setHelperSet(new HelperSet([new QuestionHelper()]));

    $result = (new CommandTester($command))->run([], ['n'], true);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Running seeder in production? (y/n)');

    $app->flush();
});

it('proceeds when the production question is confirmed', function (): void {
    $base = sys_get_temp_dir() . '/omegaseed-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new SeedCommand();
    $command->app = $app;
    $command->setHelperSet(new HelperSet([new QuestionHelper()]));

    $result = (new CommandTester($command))->run(['--class' => 'Ghost'], ['y'], true);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Running seeder in production? (y/n)')
        ->and($result->getDisplay())->toContain('Seeder class [Database\Seeders\Ghost] does not exist.');

    $app->flush();
});

it('skips the production question when forced', function (): void {
    $base = sys_get_temp_dir() . '/omegaseed-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new SeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--force' => true, '--class' => 'Ghost']);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($display)->not->toContain('Running seeder in production? (y/n)')
        ->and($display)->toContain('Seeder class [Database\Seeders\Ghost] does not exist.');

    $app->flush();
});

it('rejects both class and name-space options at the same time', function (): void {
    $base = sys_get_temp_dir() . '/omegaseed-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new SeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([
        '--force'      => true,
        '--class'      => 'ExampleSeeder',
        '--name-space' => FakeSeeder::class,
    ]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Use only one: --class or --name-space, be specific.');

    $app->flush();
});

it('reports when the default seeder class does not exist', function (): void {
    $base = sys_get_temp_dir() . '/omegaseed-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new SeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--force' => true]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Seeder class [Database\Seeders\DatabaseSeeder] does not exist.');

    $app->flush();
});

it('runs the target seeder when forced', function (): void {
    $base = sys_get_temp_dir() . '/omegaseed-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    FakeSeeder::$runs = 0;
    FakeSeeder::$throw = null;

    $app = new Application($base);

    $command = new SeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([
        '--force'      => true,
        '--name-space' => FakeSeeder::class,
    ]);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain("Running seeder: " . FakeSeeder::class)
        ->and($display)->toContain("Success run seeder: " . FakeSeeder::class)
        ->and(FakeSeeder::$runs)->toBe(1);

    $app->flush();
});

it('reports when the seeder run fails', function (): void {
    $base = sys_get_temp_dir() . '/omegaseed-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    FakeSeeder::$runs = 0;
    FakeSeeder::$throw = new \RuntimeException('Boom');

    $app = new Application($base);

    $command = new SeedCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([
        '--force'      => true,
        '--name-space' => FakeSeeder::class,
    ]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Seeding failed: Boom');

    FakeSeeder::$throw = null;

    $app->flush();
});