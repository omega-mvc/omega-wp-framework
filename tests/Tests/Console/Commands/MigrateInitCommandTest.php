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
use Omega\Console\Commands\MigrateInitCommand;
use Omega\Database\Schema\SchemaConnection;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeSchema;
use Tests\Console\Commands\Fixtures\ScriptedConnection;
use Tests\Console\Commands\Fixtures\ScriptedSchemaConnection;

covers(MigrateInitCommand::class);

it('reports when the database does not exist', function (): void {
    $base = sys_get_temp_dir() . '/omegainit-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);
    $app->set(SchemaConnection::class, new ScriptedSchemaConnection('omega_test'));

    AbstractFacade::setFacadeBase($app);

    $command = new MigrateInitCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Database `omega_test` does not exist, run `db:create` first.');

    $app->flush();
});

it('reports when the migration table already exists', function (): void {
    $base = sys_get_temp_dir() . '/omegainit-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);

    $connection = new ScriptedSchemaConnection('omega_test');
    $connection->whenQueryContains('information_schema.schemata', [['total' => '1']]);
    $app->set(SchemaConnection::class, $connection);

    $pdo = new ScriptedConnection('omega_test');
    $pdo->whenQueryContains('information_schema.tables', [['total' => '1']]);
    $app->set('database', $pdo);

    AbstractFacade::setFacadeBase($app);

    $command = new MigrateInitCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Migration table already exists in your database.');

    $app->flush();
});

it('creates the migration table when it is missing', function (): void {
    $base = sys_get_temp_dir() . '/omegainit-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);

    $connection = new ScriptedSchemaConnection('omega_test');
    $connection->whenQueryContains('information_schema.schemata', [['total' => '1']]);
    $app->set(SchemaConnection::class, $connection);

    $app->set('database', new ScriptedConnection('omega_test'));

    $schema = new FakeSchema();
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    $command = new MigrateInitCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Successfully created migration table.')
        ->and($schema->last->table)->toBe('migration');

    $app->flush();
});

it('reports when the migration table cannot be created', function (): void {
    $base = sys_get_temp_dir() . '/omegainit-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);

    $connection = new ScriptedSchemaConnection('omega_test');
    $connection->whenQueryContains('information_schema.schemata', [['total' => '1']]);
    $app->set(SchemaConnection::class, $connection);

    $app->set('database', new ScriptedConnection('omega_test'));

    $schema = new FakeSchema();
    $schema->fail();
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    $command = new MigrateInitCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Migration table cannot be created.');

    $app->flush();
});