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
use Omega\Console\Commands\DatabaseCreateCommand;
use Omega\Database\Schema\SchemaConnection;
use Omega\Facade\AbstractFacade;
use PDOException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeSchema;
use Tests\Console\Commands\Fixtures\ScriptedSchemaConnection;

covers(DatabaseCreateCommand::class);

it('aborts when the user does not confirm the creation in production', function (): void {
    $base = sys_get_temp_dir() . '/omegadb-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set(SchemaConnection::class, new ScriptedSchemaConnection('omega_test'));

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseCreateCommand();
    $command->app = $app;

    $tester = new CommandTester($command);
    $tester->setInputs(['n']);

    $result = $tester->run(['--database' => 'omega_test'], ['n'], true);

    expect($result->statusCode)->toBe(Command::INVALID)
        ->and($result->getDisplay())->toContain('The application is in PRODUCTION.')
        ->and($result->getDisplay())->toContain('Operation aborted.');

    $app->flush();
});

it('creates the database after the user confirms in production', function (): void {
    $base = sys_get_temp_dir() . '/omegadb-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $app->set('Schema', $schema);
    $app->set(SchemaConnection::class, new ScriptedSchemaConnection('omega_test'));

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseCreateCommand();
    $command->app = $app;

    $tester = new CommandTester($command);
    $tester->setInputs(['y']);

    $result = $tester->run(['--database' => 'omega_test'], ['y'], true);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Creating database `omega_test`...')
        ->and($result->getDisplay())->toContain('Successfully created database `omega_test`')
        ->and($schema->last->database)->toBe('omega_test');

    $app->flush();
});

it('skips the confirmation prompt when the no-interact option is used', function (): void {
    $base = sys_get_temp_dir() . '/omegadb-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseCreateCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--no-interact' => true, '--database' => 'app_db']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Successfully created database `app_db`')
        ->and($schema->last->database)->toBe('app_db');

    $app->flush();
});

it('reports when the database already exists', function (): void {
    $base = sys_get_temp_dir() . '/omegadb-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $schema->throwing(new PDOException('SQLSTATE[42P01]: database exists'));
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseCreateCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--no-interact' => true, '--database' => 'app_db']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Database `app_db` already exists.');

    $app->flush();
});

it('rethrows a database exception that is not about an existing database', function (): void {
    $base = sys_get_temp_dir() . '/omegadb-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $schema->throwing(new PDOException('Unexpected connection issue'));
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseCreateCommand();
    $command->app = $app;

    expect(static fn () => (new CommandTester($command))->run([
        '--no-interact' => true,
        '--database'    => 'app_db',
    ]))->toThrow(PDOException::class, 'Unexpected connection issue');

    $app->flush();
});

it('reports when the database cannot be created', function (): void {
    $base = sys_get_temp_dir() . '/omegadb-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $schema->fail();
    $app->set('Schema', $schema);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseCreateCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--no-interact' => true, '--database' => 'app_db']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Cannot create database `app_db`');

    $app->flush();
});