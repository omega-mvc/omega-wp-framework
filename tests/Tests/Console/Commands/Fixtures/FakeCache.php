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

use DateInterval;
use Omega\Cache\CacheInterface;

class FakeCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $store = [];

    private bool $clearResult = true;

    private bool $supportsClear = true;

    private ?\Throwable $clearException = null;

    public function setClearResult(bool $result): static
    {
        $this->clearResult = $result;

        return $this;
    }

    public function setClearException(\Throwable $exception): static
    {
        $this->clearException = $exception;

        return $this;
    }

    public function setSupported(bool $supported): static
    {
        $this->supportsClear = $supported;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        if ($this->clearException !== null) {
            throw $this->clearException;
        }

        return $this->clearResult;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->store[$key] ?? $default;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->store[$key] = $value;
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->store[$key]);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }

    public function increment(string $key, int $value = 1): int
    {
        $result = (is_int($this->store[$key] ?? null) ? $this->store[$key] : 0) + $value;

        $this->store[$key] = $result;

        return $result;
    }

    public function decrement(string $key, int $value = 1): int
    {
        $result = (is_int($this->store[$key] ?? null) ? $this->store[$key] : 0) - $value;

        $this->store[$key] = $result;

        return $result;
    }

    public function remember(string $key, \Closure $callback, int|DateInterval|null $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function isSupported(): bool
    {
        return $this->supportsClear;
    }
}