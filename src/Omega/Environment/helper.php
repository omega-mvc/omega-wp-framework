<?php

declare(strict_types=1);

namespace Omega\Environment;

use Omega\Environment\Env as GetEnv;

/**
 * Retrieve an environment variable value.
 *
 * This helper provides access to environment configuration values
 * through the internal Env component. If the given key does not exist,
 * the provided default value will be returned instead.
 *
 * The function is declared in the `Omega\Environment` namespace, so it cannot
 * collide with a global `env()` (or any other namespace's `env()`); the
 * `function_exists()` guard used by WordPress-style global helpers is therefore
 * unnecessary and would in fact be harmful: it would check the *global* scope
 * and silently skip this declaration if a global `env()` happened to exist.
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
