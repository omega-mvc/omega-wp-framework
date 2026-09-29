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

use Omega\Database\ConnectionInterface;

class ScriptedConnection implements ConnectionInterface
{
    /** @var array<string, array<array<string, mixed>>|array<string, bool>> */
    private array $scriptMap = [];

    /** @var array<int, array<string, mixed>> */
    private array $defaultResultset = [];

    private bool $executeResult = true;

    private bool $throwOnExecute = false;

    private string $database = 'omega_test';

    /** @var list<string> */
    public array $queries = [];

    /** @var array<string, self> */
    public static array $shared = [];

    public function __construct(string $database = 'omega_test')
    {
        $this->database = $database;
    }

    public static function global(): self
    {
        return self::$shared['default'] ??= new self();
    }

    public static function shared(string $name): self
    {
        return self::$shared[$name] ??= new self();
    }

    public static function resetShared(): void
    {
        self::$shared = [];
    }

    public function getInstance(): self
    {
        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function setDefaultResultset(array $rows): static
    {
        $this->defaultResultset = $rows;

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function whenQueryContains(string $needle, array $rows): static
    {
        $this->scriptMap[$needle] = $rows;

        return $this;
    }

    public function failingOn(string $needle): static
    {
        $this->scriptMap[$needle] = ['__throw__' => true];

        return $this;
    }

    public function failNextExecute(): static
    {
        $this->throwOnExecute = true;

        return $this;
    }

    public function setExecuteResult(bool $result): static
    {
        $this->executeResult = $result;

        return $this;
    }

    public function getDatabaseName(): string
    {
        return $this->database;
    }

    /**
     * @param array<int|string, mixed>|null $bindings
     */
    public function query(string $query, ?array $bindings = null): static
    {
        $this->queries[] = $query;

        return $this;
    }

    public function bind(string|int $param, mixed $value, int|null $type = null): static
    {
        return $this;
    }

    public function execute(): bool
    {
        if ($this->throwOnExecute) {
            $this->throwOnExecute = false;

            throw new \PDOException('Scripted failure');
        }

        $last = end($this->queries);

        if (is_string($last) && $this->isFailing($last)) {
            throw new \PDOException('Scripted SQL failure');
        }

        return $this->executeResult;
    }

    /** @return array<int, array<string, mixed>>|false */
    public function resultset(): array|false
    {
        $sql = end($this->queries);

        if ($sql === false) {
            return [];
        }

        return $this->resolve($sql);
    }

    /** @return array<string, mixed>|false */
    public function single(): array|false
    {
        $rows = $this->resultset();

        return is_array($rows) && $rows !== [] ? $rows[0] : false;
    }

    public function rowCount(): int
    {
        return 1;
    }

    public function lastInsertId(): string|false
    {
        return '1';
    }

    public function transaction(callable $callable): bool
    {
        return (bool) $callable($this);
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function endTransaction(): bool
    {
        return true;
    }

    public function cancelTransaction(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function flushLogs(): void
    {
    }

    public function getLogs(): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    private function resolve(string $sql): array
    {
        $match = null;

        foreach (array_keys($this->scriptMap) as $needle) {
            if (str_contains($sql, (string) $needle)) {
                if ($match === null || strlen((string) $needle) > strlen((string) $match)) {
                    $match = $needle;
                }
            }
        }

        if ($match === null) {
            return $this->defaultResultset;
        }

        $value = $this->scriptMap[$match] ?? [];

        if (($value['__throw__'] ?? false) === true) {
            throw new \PDOException('Scripted SQL failure');
        }

        /** @var array<int, array<string, mixed>> $value */
        return $value;
    }

    private function isFailing(string $sql): bool
    {
        foreach ($this->scriptMap as $needle => $value) {
            if (str_contains($sql, (string) $needle) && ($value['__throw__'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }
}