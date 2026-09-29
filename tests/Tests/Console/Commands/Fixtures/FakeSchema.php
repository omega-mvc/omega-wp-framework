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

use PDOException;

final class FakeSchemaBuilder
{
    public string $database = '';

    public string $table = '';

    public bool $ifExists = false;

    private bool $result = true;

    private ?PDOException $throw = null;

    public function database(string $name): static
    {
        $this->database = $name;

        return $this;
    }

    public function ifExists(): static
    {
        $this->ifExists = true;

        return $this;
    }

    public function fail(): static
    {
        $this->result = false;

        return $this;
    }

    public function throwing(PDOException $e): static
    {
        $this->throw = $e;

        return $this;
    }

    public function execute(): bool
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        return $this->result;
    }
}

final class FakeSchema
{
    public FakeSchemaBuilder $last;

    private bool $fail = false;

    private ?PDOException $throw = null;

    public function fail(): void
    {
        $this->fail = true;
    }

    public function throwing(PDOException $e): void
    {
        $this->throw = $e;
    }

    private function builder(): FakeSchemaBuilder
    {
        $builder = new FakeSchemaBuilder();

        if ($this->fail) {
            $builder->fail();
        }

        if ($this->throw !== null) {
            $builder->throwing($this->throw);
        }

        return $this->last = $builder;
    }

    public function create(): FakeSchemaBuilder
    {
        return $this->builder();
    }

    public function drop(): FakeSchemaBuilder
    {
        return $this->builder();
    }

    public function table(string $table, callable $blueprint): FakeSchemaBuilder
    {
        $builder = $this->builder();
        $builder->table = $table;

        return $builder;
    }
}