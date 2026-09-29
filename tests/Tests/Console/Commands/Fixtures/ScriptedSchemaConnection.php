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

use Omega\Database\Schema\SchemaConnectionInterface;

class ScriptedSchemaConnection extends ScriptedConnection implements SchemaConnectionInterface
{
    public static function shared(string $name): static
    {
        $connection = self::$shared[$name] ?? null;

        if (!$connection instanceof static) {
            $connection = new self();

            self::$shared[$name] = $connection;
        }

        return $connection;
    }

    public static function global(): static
    {
        return self::shared('default');
    }

    public function getDatabase(): string
    {
        return $this->getDatabaseName();
    }
}