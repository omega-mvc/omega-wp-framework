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
use Omega\Console\Commands\CronWorkCommand;
use Omega\Cron\Facade\Schedule as Scheduler;
use Omega\Cron\Schedule;
use Omega\Facade\AbstractFacade;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(CronWorkCommand::class);

it('exits immediately when the worker flag is already set', function (): void {
    $base = sys_get_temp_dir() . '/omegacw-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('schedule', new Schedule(time()));

    AbstractFacade::flushInstance();
    Scheduler::setFacadeBase($app);

    $command = new CronWorkCommand();
    $command->app = $app;

    (new ReflectionClass($command))->getProperty('shouldExit')->setValue($command, true);

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Omega Cron Worker')
        ->and($result->getDisplay())->toContain('Watching and executing scheduled jobs every minute...')
        ->and($result->getDisplay())->toContain('Press CTRL+C to stop the worker.');

    $app->flush();
});