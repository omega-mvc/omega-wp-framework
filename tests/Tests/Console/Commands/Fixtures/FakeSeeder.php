<?php

declare(strict_types=1);

/*
 * Part of Omega - Tests\Console\Commands Provision.
 *
 * @link      https://omegamvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright 2025-2026 The Omega MVC Framework
 * @license   https://opensource.org/license/mit The MIT License
 * @version   2.0.0
 */

namespace Tests\Console\Commands\Fixtures;

use Throwable;

/**
 * Scripted seeder for the SeedCommand tests.
 */
final class FakeSeeder
{
    public static int $runs = 0;

    public static ?Throwable $throw = null;

    public function run(): void
    {
        self::$runs++;

        if (self::$throw !== null) {
            throw self::$throw;
        }
    }
}