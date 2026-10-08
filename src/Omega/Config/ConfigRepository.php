<?php

/**
 * Part of Omega - Config Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Config;

use Omega\Config\ConfigServiceProvider;
use stdClass;

use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function is_string;
use function sanitize_text_field;
use function strtolower;
use function trim;

/**
 * ConfigRepository
 *
 * Provides read-only access to a hierarchical configuration array using dot notation.
 * The configuration is injected at construction time and remains immutable during runtime.
 *
 * This repository is designed for static application configuration such as feature flags,
 * service definitions, and environment-specific constants.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class ConfigRepository
{
    #region Lifecycle
    /**
     * ConfigRepository constructor.
     *
     * Initializes the repository with a static configuration array.
     *
     * @param array<int|string, mixed> $config Initial configuration data used as the source of truth.
     */
    public function __construct(protected array $config)
    {
    }
    #endregion

    #region Retrieval
    /**
     * Retrieve a configuration value using dot notation.
     *
     * Traverses a nested configuration array using a dot-separated key path.
     * If the key does not exist, the provided default value is returned.
     *
     * @param string $name Dot-notated configuration key (e.g. "database.connections.mysql").
     * @param mixed $default Default value returned if the key is not found.
     * @return mixed The resolved configuration value or the default value if not found.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->traverseArray($this->config, explode('.', $name), $default);
    }

    /**
     * Determine whether a configuration value exists.
     *
     * Lookup resolves only by exact dot-notated path traversal.
     *
     * @param string $key Configuration key to check.
     * @return bool True if the configuration value exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return $this->get($key, '__missing__') !== '__missing__';
    }

    /**
     * Retrieve the entire configuration array.
     *
     * @return array<int|string, mixed> The full configuration dataset.
     */
    public function getAll(): array
    {
        return $this->config;
    }
    #endregion

    #region Casting
    /**
     * Retrieve a configuration value and cast it to a sanitized string.
     *
     * The value is passed through WordPress sanitize_text_field() before being returned.
     * Non-string scalar values are converted to their string representation instead of
     * being discarded; null and array values fall back to the declared default.
     *
     * @param string $name Dot-notated configuration key.
     * @param string|null $default Default value used if the key is not found.
     * @return string The sanitized string value.
     */
    public function string(string $name, ?string $default = null): string
    {
        $value = $this->get($name, $default);

        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_scalar($value)) {
            return sanitize_text_field((string) $value);
        }

        return sanitize_text_field((string) ($default ?? ''));
    }

    /**
     * Retrieve a configuration value and cast it to boolean.
     *
     * Accepts native booleans as well as common string and numeric
     * representations ("1", "true", "yes", "on") so values coming from
     * environment sources are not silently misinterpreted.
     *
     * @param string $name Dot-notated configuration key.
     * @param bool|null $default Default value used if the key is not found
     *                           or cannot be interpreted as a boolean.
     * @return bool The resolved boolean value.
     */
    public function boolean(string $name, ?bool $default = null): bool
    {
        $value = $this->get($name, $default);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (!is_string($value)) {
            return $default ?? false;
        }

        $value = strtolower(trim($value));

        $isTruthy = in_array($value, ['1', 'true', 'yes', 'on'], true);

        if ($isTruthy) {
            return true;
        }

        $isFalsy = in_array($value, ['0', 'false', 'no', 'off'], true);

        if ($isFalsy) {
            return false;
        }

        return $default ?? false;
    }

    /**
     * Retrieve a configuration value and cast it to integer.
     *
     * The value is explicitly cast to int after retrieval.
     *
     * @param string $name Dot-notated configuration key.
     * @param int|null $default Default value used if the key is not found.
     * @return int The resolved integer value.
     */
    public function integer(string $name, ?int $default = null): int
    {
        $value = $this->get($name, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            return (int) $value;
        }

        return $default ?? 0;
    }
    #endregion

    #region Traversal
    /**
     * Traverse a nested configuration array using a sequence of key segments.
     *
     * Each segment is resolved against the current nesting level until the
     * target value is reached. If any segment cannot be resolved, the provided
     * default value is returned instead.
     *
     * @param array<int|string, mixed> $data Configuration array to traverse.
     * @param array<int, string> $segments Ordered key segments to resolve.
     * @param mixed $default Value returned when the path cannot be resolved.
     * @return mixed The resolved configuration value or the default value.
     */
    private function traverseArray(array $data, array $segments, mixed $default): mixed
    {
        $marker = new stdClass();

        $result = array_reduce(
            $segments,
            function (mixed $carry, string $segment) use ($marker): mixed {
                if ($carry === $marker) {
                    return $marker;
                }

                if (!is_array($carry) || !isset($carry[$segment])) {
                    return $marker;
                }

                return $carry[$segment];
            },
            $data
        );

        return $result === $marker ? $default : $result;
    }
    #endregion
}
