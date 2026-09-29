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
use Omega\Console\Commands\DatabaseWipeCommand;
use Omega\Database\Schema\SchemaConnection;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeSchema;
use Tests\Console\Commands\Fixtures\ScriptedSchemaConnection;

covers(DatabaseWipeCommand::class);

it('aborts when the user does not confirm the drop in production', function (): void {
    $base = sys_get_temp_dir() . '/omegadw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);
    $app->set(SchemaConnection::class, $conn = new ScriptedSchemaConnection('omega_test'));
    $conn->whenQueryContains('information_schema.schemata', [['total' => '1']]);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseWipeCommand();
    $command->app = $app;

    $tester = new CommandTester($command);
    $tester->setInputs(['n']);

    $result = $tester->run(['--database' => 'omega_test'], ['n'], true);

    expect($result->statusCode)->toBe(Command::INVALID)
        ->and($result->getDisplay())->toContain('The application is in PRODUCTION.')
        ->and($result->getDisplay())->toContain('Operation aborted.');

    $app->flush();
});

it('drops the database after the user confirms in production', function (): void {
    $base = sys_get_temp_dir() . '/omegadw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $app->set('Schema', $schema);
    $app->set('dsn.sql', ['driver' => 'mysql']);
    $app->set(SchemaConnection::class, $conn = new ScriptedSchemaConnection('omega_test'));
    $conn->whenQueryContains('information_schema.schemata', [['total' => '1']]);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseWipeCommand();
    $command->app = $app;

    $tester = new CommandTester($command);
    $tester->setInputs(['y']);

    $result = $tester->run(['--database' => 'omega_test'], ['y'], true);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Trying to drop database `omega_test`...')
        ->and($result->getDisplay())->toContain('Successfully dropped database `omega_test`')
        ->and($schema->last->database)->toBe('omega_test')
        ->and($schema->last->ifExists)->toBeTrue();

    $app->flush();
});

it('reports when the database does not exist', function (): void {
    $base = sys_get_temp_dir() . '/omegadw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('dsn.sql', ['driver' => 'mysql']);
    $app->set(SchemaConnection::class, new ScriptedSchemaConnection('omega_test'));

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseWipeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--no-interact' => true, '--database' => 'missing_db']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Database `missing_db` does not exist, nothing to drop.');

    $app->flush();
});

it('drops the database without confirmation in no-interact mode', function (): void {
    $base = sys_get_temp_dir() . '/omegadw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $app->set('Schema', $schema);
    $app->set('dsn.sql', ['driver' => 'mysql']);
    $app->set(SchemaConnection::class, $conn = new ScriptedSchemaConnection('omega_test'));
    $conn->whenQueryContains('information_schema.schemata', [['total' => '1']]);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseWipeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--no-interact' => true, '--database' => 'omega_test']);

    $display = $result->getDisplay();

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($display)->not->toContain('The application is in PRODUCTION.')
        ->and($display)->toContain('Successfully dropped database `omega_test`');

    $app->flush();
});

it('reports when the database cannot be dropped', function (): void {
    $base = sys_get_temp_dir() . '/omegadw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $schema = new FakeSchema();
    $schema->fail();
    $app->set('Schema', $schema);
    $app->set('dsn.sql', ['driver' => 'mysql']);
    $app->set(SchemaConnection::class, $conn = new ScriptedSchemaConnection('omega_test'));
    $conn->whenQueryContains('information_schema.schemata', [['total' => '1']]);

    AbstractFacade::setFacadeBase($app);

    $command = new DatabaseWipeCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--no-interact' => true, '--database' => 'omega_test']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Cannot drop database `omega_test`');

    $app->flush();
});