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
use Omega\Console\Commands\MigrateFreshCommand;
use Omega\Database\Schema\SchemaConnection;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\ConsoleHarness;
use Tests\Console\Commands\Fixtures\FakeSchema;
use Tests\Console\Commands\Fixtures\FakeSeeder;
use Tests\Console\Commands\Fixtures\ScriptedConnection;
use Tests\Console\Commands\Fixtures\ScriptedSchemaConnection;

covers(MigrateFreshCommand::class);

function freshMigrationsDir(string $base): string
{
    $dir = $base . '/database/migrations';
    mkdir($dir, 0777, true);

    copy(
        __DIR__ . '/Fixtures/migrations/create_users_table.php',
        $dir . '/create_users_table.php'
    );

    return $dir;
}

it('reports when the db:wipe command cannot be dispatched', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new MigrateFreshCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--force' => true]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Unable to execute command 'db:wipe': The application instance is not available.");

    $app->flush();
});

it('aborts when the production confirmation is declined', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $command = new MigrateFreshCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([], ['n'], true);

    expect($result->statusCode)->toBe(Command::INVALID)
        ->and($result->getDisplay())->toContain('Running migration/database in production? Continue?');

    $app->flush();
});

it('propagates the failure when the database does not exist', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);
    $app->set(SchemaConnection::class, new ScriptedSchemaConnection('omega_test'));

    AbstractFacade::setFacadeBase($app);

    [$application] = ConsoleHarness::make($app);

    $command = new MigrateFreshCommand();
    $command->app = $app;
    $command->setApplication($application);

    $result = (new CommandTester($command))->run(['--force' => true]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Database `omega_test` does not exist, nothing to drop.');

    $app->flush();
});

it('drops, recreates and migrates the database from scratch', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    ScriptedConnection::resetShared();
    freshMigrationsDir($base);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);

    $connection = new ScriptedSchemaConnection('omega_test');
    $connection->whenQueryContains('information_schema.schemata', [['total' => '1']]);
    $app->set(SchemaConnection::class, $connection);

    $app->set('database', new ScriptedConnection('omega_test'));

    $schema = new FakeSchema();
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    [$application] = ConsoleHarness::make($app);

    $command = new MigrateFreshCommand();
    $command->app = $app;
    $command->setApplication($application);

    $result = (new CommandTester($command))->run(['--force' => true]);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('Successfully dropped database `omega_test`')
        ->and($display)->toContain('Successfully created database `omega_test`')
        ->and($display)->toContain('Successfully created migration table.')
        ->and($display)->toContain('Running migration')
        ->and($display)->toContain('create_users_table')
        ->and($display)->toContain('DONE')
        ->and($schema->last->table)->toBe('migration');

    $app->flush();
});

it('prints the migration SQL when running in dry-run mode', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    ScriptedConnection::resetShared();
    freshMigrationsDir($base);

    $app = new Application($base);

    $command = new MigrateFreshCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--force' => true, '--dry-run' => true]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('CREATE TABLE users (id INT)');

    $app->flush();
});

it('reports when the path.migrations binding is not a string', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('path.migrations', 123);

    $command = new MigrateFreshCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--force' => true, '--dry-run' => true]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.migrations" binding must resolve to a string path.');

    $app->flush();
});

it('seeds the database after running the migrations', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    FakeSeeder::$runs = 0;
    FakeSeeder::$throw = null;

    $app = new Application($base);
    $app->set('environment', 'dev');
    $app->set('dsn.sql', ['driver' => 'mysql']);

    $connection = new ScriptedSchemaConnection('omega_test');
    $connection->whenQueryContains('information_schema.schemata', [['total' => '1']]);
    $app->set(SchemaConnection::class, $connection);

    $app->set('database', new ScriptedConnection('omega_test'));

    $schema = new FakeSchema();
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    [$application] = ConsoleHarness::make($app);

    $command = new MigrateFreshCommand();
    $command->app = $app;
    $command->setApplication($application);

    $result = (new CommandTester($command))->run([
        '--force'           => true,
        '--seed'            => true,
        '--seed-namespace'  => FakeSeeder::class,
    ]);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('Running seeder: ' . FakeSeeder::class)
        ->and($display)->toContain('Success run seeder: ' . FakeSeeder::class)
        ->and(FakeSeeder::$runs)->toBe(1);

    FakeSeeder::$throw = null;

    $app->flush();
});

it('marks the migration as failed when a query throws', function (): void {
    $base = sys_get_temp_dir() . '/omegafresh-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    ScriptedConnection::resetShared();
    freshMigrationsDir($base);

    ScriptedSchemaConnection::shared('users_raw')->failNextExecute();

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);

    $connection = new ScriptedSchemaConnection('omega_test');
    $connection->whenQueryContains('information_schema.schemata', [['total' => '1']]);
    $app->set(SchemaConnection::class, $connection);

    $app->set('database', new ScriptedConnection('omega_test'));

    $schema = new FakeSchema();
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    [$application] = ConsoleHarness::make($app);

    $command = new MigrateFreshCommand();
    $command->app = $app;
    $command->setApplication($application);

    $result = (new CommandTester($command))->run(['--force' => true]);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('Scripted failure')
        ->and($display)->toContain('create_users_table')
        ->and($display)->toContain('FAIL');

    $app->flush();
});