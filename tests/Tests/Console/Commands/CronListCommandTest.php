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
use Omega\Console\Commands\CronListCommand;
use Omega\Cron\Facade\Schedule as Scheduler;
use Omega\Cron\Schedule;
use Omega\Facade\AbstractFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(CronListCommand::class);

it('reports when there are no scheduled jobs', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);
    $app->set('schedule', new Schedule(time()));

    AbstractFacade::flushInstance();
    Scheduler::setFacadeBase($app);

    $command = new CronListCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('No scheduled jobs found.');

    $app->flush();
});

it('lists the scheduled jobs', function (): void {
    $base = sys_get_temp_dir() . '/omegacl-' . bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = new Application($base);

    $schedule = new Schedule(time());
    $schedule->call(static function (): void {})->hourly()->eventName('Send mail');
    $schedule->call(static function (): void {})->justInTime()->anonymously()->eventName('Backup DB');

    $app->set('schedule', $schedule);

    AbstractFacade::flushInstance();
    Scheduler::setFacadeBase($app);

    $command = new CronListCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Send mail')
        ->and($result->getDisplay())->toContain('Backup DB')
        ->and($result->getDisplay())->toContain('hourly')
        ->and($result->getDisplay())->toContain('justInTime')
        ->and($result->getDisplay())->toContain('(Anonymous)')
        ->and($result->getDisplay())->toContain('Showing [2] scheduled jobs.');

    $app->flush();
});