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
use Omega\Console\Commands\MakeModelCommand;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\ScriptedConnection;

use function Tests\Console\Commands\make_model_app;

covers(MakeModelCommand::class);

/**
 * @return array{0: Application, 1: string}
 */
function make_model_app(): array
{
    $base = sys_get_temp_dir() . '/omegamodel-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $save = $base . '/models/';
    mkdir($save, 0777, true);

    $app = new Application($base);
    $app->set('path.model', $save);

    return [$app, $save];
}

it('creates a model file for make:model', function (): void {
    [$app, $save] = make_model_app();

    $command = new MakeModelCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    $content = (string) file_get_contents($save . 'UserProfile.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Making model file...')
        ->and($result->getDisplay())->toContain('Model [app/Models/UserProfile] created successfully.')
        ->and(file_exists($save . 'UserProfile.php'))->toBeTrue()
        ->and($content)->toContain('namespace App\\Models;')
        ->and($content)->toContain('extends Model')
        ->and($content)->toContain("= 'userprofile'");

    $app->flush();
});

it('rejects an empty name argument for make:model', function (): void {
    [$app] = make_model_app();

    $command = new MakeModelCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => '   ']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "name" argument must be a non-empty string.');

    $app->flush();
});

it('fails when the path.model binding is not a string', function (): void {
    [$app] = make_model_app();
    $app->set('path.model', 123);

    $command = new MakeModelCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The "path.model" binding must resolve to a string path.');

    $app->flush();
});

it('reports that the model file already exists without force', function (): void {
    [$app, $save] = make_model_app();
    file_put_contents($save . 'UserProfile.php', 'x');

    $command = new MakeModelCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('File already exists.')
        ->and($result->getDisplay())->toContain('Failed to create model file. Use --force to overwrite.');

    $app->flush();
});

it('overwrites an existing model file with --force', function (): void {
    [$app, $save] = make_model_app();
    file_put_contents($save . 'UserProfile.php', 'old');

    $command = new MakeModelCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile', '--force' => true]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and((string) file_get_contents($save . 'UserProfile.php'))->not->toContain('old');

    $app->flush();
});

it('fails when the model file cannot be written', function (): void {
    $base = sys_get_temp_dir() . '/omegamodel-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $blocker = $base . 'blocker';
    touch($blocker);
    $app->set('path.model', $blocker . '/models/');

    $command = new MakeModelCommand();
    $command->app = $app;

    set_error_handler(static fn (int $severity, string $message): bool => true);

    $result = (new CommandTester($command))->run(['name' => 'UserProfile']);

    restore_error_handler();

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Failed to write model file to disk.');

    $app->flush();
});

it('generates table columns as docblock properties with --table-name', function (): void {
    ScriptedConnection::resetShared();

    [$app, $save] = make_model_app();

    $connection = ScriptedConnection::shared('model-info')
        ->whenQueryContains('INFORMATION_SCHEMA', [
            ['COLUMN_NAME' => 'id', 'COLUMN_KEY' => 'PRI'],
            ['COLUMN_NAME' => 'email', 'COLUMN_KEY' => ''],
        ]);

    $app->set('database', $connection);
    AbstractFacade::setFacadeBase($app);

    $command = new MakeModelCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile', '--table-name' => 'users']);

    $content = (string) file_get_contents($save . 'UserProfile.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Getting information from table [users]...')
        ->and($content)->toContain('@property mixed $id')
        ->and($content)->toContain('@property mixed $email')
        ->and($content)->toContain("= 'users'")
        ->and($content)->toContain("= 'id'");

    $app->flush();
});

it('warns and continues when reading table information fails', function (): void {
    ScriptedConnection::resetShared();

    [$app, $save] = make_model_app();

    $connection = ScriptedConnection::shared('model-fail')
        ->failingOn('INFORMATION_SCHEMA');

    $app->set('database', $connection);
    AbstractFacade::setFacadeBase($app);

    $command = new MakeModelCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['name' => 'UserProfile', '--table-name' => 'users']);

    $content = (string) file_get_contents($save . 'UserProfile.php');

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Database warning: Scripted SQL failure')
        ->and($content)->toContain("= 'users'");

    $app->flush();
});