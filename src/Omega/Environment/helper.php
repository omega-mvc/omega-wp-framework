<?php

declare(strict_types=1);

namespace Omega\Environment;

use Omega\Environment\Env as GetEnv;

if (!function_exists('env')) {
    /**
     * Retrieve an environment variable value.
     *
     * This helper provides access to environment configuration values
     * through the internal Env component. If the given key does not exist,
     * the provided default value will be returned instead.
     *
     * Example usage:
     * ```php
     * $appEnv = env('APP_ENV', 'production');
     * ```
     *
     * @param string $key The environment variable key.
     * @param mixed $default Optional default value returned if the key is not found.
     * @return mixed The environment value or the default if not set.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return GetEnv::get($key, $default);
    }
}