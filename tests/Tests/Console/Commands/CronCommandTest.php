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
use Omega\Console\Commands\CronCommand;
use Omega\Cron\Facade\Schedule as Scheduler;
use Omega\Cron\Schedule;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Commands\Fixtures\FakeCronLogger;

covers(CronCommand::class);

it('executes scheduled jobs for cron:run', function (): void {
    $base = sys_get_temp_dir() . '/omegacr-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $executed = false;

    $schedule = new Schedule(time());
    $schedule->setLogger(new FakeCronLogger());
    $schedule->call(static function () use (&$executed): void {
        $executed = true;
    })->justInTime()->eventName('notify');

    $app->set('schedule', $schedule);

    AbstractFacade::flushInstance();
    Scheduler::setFacadeBase($app);

    $command = new CronCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Cron jobs executed successfully in')
        ->and($executed)->toBeTrue();

    $app->flush();
});

it('succeeds when there are no scheduled jobs', function (): void {
    $base = sys_get_temp_dir() . '/omegacr-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('schedule', new Schedule(time()));

    AbstractFacade::flushInstance();
    Scheduler::setFacadeBase($app);

    $command = new CronCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Cron jobs executed successfully in');

    $app->flush();
});