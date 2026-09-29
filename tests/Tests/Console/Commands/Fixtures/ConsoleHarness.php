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

namespace Tests\Console\Commands\Fixtures;

use Omega\Application\Application;
use Omega\Console\AbstractCommand;
use Omega\Console\Commands\DatabaseCreateCommand;
use Omega\Console\Commands\DatabaseWipeCommand;
use Omega\Console\Commands\MigrateInitCommand;
use Omega\Console\Commands\MigrateResetCommand;
use Omega\Console\Commands\MigrateRunCommand;
use Omega\Console\Commands\SeedCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * Shared Symfony console application wiring the migration-related commands
 * to the same Omega application instance under test.
 */
final class ConsoleHarness
{
    /**
     * Build a Symfony console application with the migrate commands registered.
     *
     * @return array{0: ConsoleApplication, 1: list<AbstractCommand>}
     */
    public static function make(Application $app): array
    {
        $application = new ConsoleApplication();

        /** @var list<AbstractCommand> $commands */
        $commands = [
            new DatabaseWipeCommand(),
            new DatabaseCreateCommand(),
            new MigrateInitCommand(),
            new MigrateResetCommand(),
            new MigrateRunCommand(),
            new SeedCommand(),
        ];

        foreach ($commands as $command) {
            $command->app = $app;
        }

        $application->addCommands($commands);

        return [$application, $commands];
    }
}