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
use Omega\Console\Commands\QueueWorkCommand;
use Omega\Facade\AbstractFacade;
use Omega\Queue\Facade\Queue;
use Omega\Queue\QueueManager;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeQueueAdapter;

use function bin2hex;
use function function_exists;
use function posix_getpid;
use function posix_kill;
use function random_bytes;
use function sys_get_temp_dir;

covers(Application::class);
covers(Queue::class);
covers(QueueManager::class);
covers(QueueWorkCommand::class);

/**
 * @return array{0: Application, 1: FakeQueueAdapter}
 */
function queue_work_app(): array
{
    $base = sys_get_temp_dir() . '/omegaqw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app     = new Application($base);
    $adapter = new FakeQueueAdapter();
    $app->set('queue', new QueueManager('database', $adapter));

    return [$app, $adapter];
}

it('fails when the queue manager is not bound', function (): void {
    $base = sys_get_temp_dir() . '/omegaqw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app     = new Application($base);
    $command = new QueueWorkCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Queue is not set yet.');

    $app->flush();
});

it('exits immediately when the worker flag is already set', function (): void {
    [$app] = queue_work_app();

    AbstractFacade::flushInstance();
    Queue::setFacadeBase($app);

    $command = new QueueWorkCommand();
    $command->app = $app;

    (new ReflectionClass($command))->getProperty('shouldExit')->setValue($command, true);

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Omega Queue Worker')
        ->and($result->getDisplay())->toContain('Processing jobs from the queue...')
        ->and($result->getDisplay())->toContain('Press CTRL+C to stop the worker.');

    $app->flush();
});

it('processes a job and deletes it on success', function (): void {
    [$app, $adapter] = queue_work_app();

    AbstractFacade::flushInstance();
    Queue::setFacadeBase($app);

    $adapter->push(function (): void {
        posix_kill(posix_getpid(), SIGINT);
    });

    $command = new QueueWorkCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Processing job #1 from queue "default"')
        ->and($result->getDisplay())->toContain('Done!')
        ->and($adapter->getDeletedJobs())->toHaveCount(1);

    $app->flush();
})->skip(!function_exists('pcntl_async_signals') || !function_exists('posix_kill'), 'Requires the pcntl extension to stop the worker loop.');

it('marks a job as failed when processing throws', function (): void {
    [$app, $adapter] = queue_work_app();

    AbstractFacade::flushInstance();
    Queue::setFacadeBase($app);

    $adapter->push(function (): void {
        posix_kill(posix_getpid(), SIGINT);

        throw new RuntimeException('boom');
    });

    $command = new QueueWorkCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Failed: boom')
        ->and($adapter->getFailedJobs())->toHaveCount(1);

    $app->flush();
})->skip(!function_exists('pcntl_async_signals') || !function_exists('posix_kill'), 'Requires the pcntl extension to stop the worker loop.');