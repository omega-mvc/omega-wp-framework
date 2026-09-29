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
use Omega\Console\Commands\DatabaseShowCommand;
use Omega\Database\Schema\SchemaConnection;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\ScriptedConnection;
use Tests\Console\Commands\Fixtures\ScriptedSchemaConnection;

covers(DatabaseShowCommand::class);

it('lists the tables of the database with their sizes', function (): void {
    $base = sys_get_temp_dir() . '/omegads-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $connection = new ScriptedConnection('omega_test');
    $connection->whenQueryContains('information_schema.tables', [
        ['table_name' => 'users', 'create_time' => '2026-01-01 12:00:00', 'size' => '3'],
        ['table_name' => 'posts', 'create_time' => '2026-01-02 12:00:00', 'size' => '8'],
    ]);
    $app->set('database', $connection);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseShowCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--database' => 'omega_test']);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('Showing database: omega_test')
        ->and($display)->toContain('users')
        ->and($display)->toContain('3 MB')
        ->and($display)->toContain('posts')
        ->and($display)->toContain('8 MB')
        ->and($display)->toContain('2026-01-01 12:00:00');

    $app->flush();
});

it('reports when the database is empty', function (): void {
    $base = sys_get_temp_dir() . '/omegads-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $connection = new ScriptedConnection('omega_test');
    $connection->whenQueryContains('information_schema.tables', []);
    $app->set('database', $connection);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseShowCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--database' => 'omega_test']);

    expect($result->statusCode)->toBe(Command::INVALID)
        ->and($result->getDisplay())->toContain('Database is empty, try to run migration.');

    $app->flush();
});

it('uses the configured schema connection to resolve the default database name', function (): void {
    $base = sys_get_temp_dir() . '/omegads-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set(SchemaConnection::class, new ScriptedSchemaConnection('shop_db'));

    $connection = new ScriptedConnection('shop_db');
    $connection->whenQueryContains('information_schema.tables', [
        ['table_name' => 'products', 'create_time' => '2026-03-01 10:00:00', 'size' => '1'],
    ]);
    $app->set('database', $connection);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseShowCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Showing database: shop_db');

    $app->flush();
});

it('shows the columns of the given table with their attributes', function (): void {
    $base = sys_get_temp_dir() . '/omegads-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $connection = new ScriptedConnection('omega_test');
    $connection->whenQueryContains('INFORMATION_SCHEMA.COLUMNS', [
        ['COLUMN_NAME' => 'id', 'COLUMN_TYPE' => 'int(11)', 'COLUMN_KEY' => 'PRI', 'IS_NULLABLE' => 'NO'],
        ['COLUMN_NAME' => 'name', 'COLUMN_TYPE' => 'varchar(255)', 'COLUMN_KEY' => null, 'IS_NULLABLE' => 'YES'],
    ]);
    $app->set('database', $connection);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseShowCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--database' => 'omega_test', '--table-name' => 'users']);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->toContain('Columns of table: users')
        ->and($display)->toContain('id')
        ->and($display)->toContain('int(11)')
        ->and($display)->toContain('primary')
        ->and($display)->toContain('name')
        ->and($display)->toContain('varchar(255)')
        ->and($display)->toContain('nullable');

    $app->flush();
});

it('reports when the given table does not exist or has no columns', function (): void {
    $base = sys_get_temp_dir() . '/omegads-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $connection = new ScriptedConnection('omega_test');
    $connection->whenQueryContains('INFORMATION_SCHEMA.COLUMNS', []);
    $app->set('database', $connection);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseShowCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--database' => 'omega_test', '--table-name' => 'ghosts']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Table `ghosts` does not exist or has no columns.');

    $app->flush();
});