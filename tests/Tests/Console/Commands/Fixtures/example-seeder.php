<?php

declare(strict_types=1);

/**
 * Part of Omega - Tests\Console\Commands\Fixtures.
 *
 * @link      https://omega-mvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Axel Magold (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

namespace Database\Seeders;

use Omega\Database\ConnectionInterface;
use Omega\Database\Seeder\AbstractSeeder;
use RuntimeException;

class ExampleSeeder extends AbstractSeeder
{
    public function __construct(ConnectionInterface $pdo)
    {
        parent::__construct($pdo);
    }

    public function run(): void
    {
    }
}

class ThrowingSeeder extends AbstractSeeder
{
    public function __construct(ConnectionInterface $pdo)
    {
        parent::__construct($pdo);
    }

    public function run(): void
    {
        throw new RuntimeException('boom');
    }
}

class AnonymousSeeder
{
    public function run(): void
    {
        throw new RuntimeException('Anonymous boom');
    }
}