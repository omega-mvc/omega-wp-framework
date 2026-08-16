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

use function array_find;
use function array_reduce;
use function array_walk;
use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function sanitize_text_field;
use function str_replace;
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
    #region Properties
    /** @var array<string, mixed> Flat lookup index for fast configuration value resolution. */
    private array $index = [];
    #endregion

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
        $this->buildIndex($this->config);
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
        $value = $this->resolveFromIndex($name);

        if ($value !== null) {
            return $value;
        }

        $value = $this->traverseArray($this->config, explode('.', $name), $default);

        if ($value !== $default) {
            return $value;
        }

        return $default;
    }

    /**
     * Determine whether a configuration value exists.
     *
     * The lookup supports both dot-separated and underscore-separated keys.
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
     *
     * @param string $name Dot-notated configuration key.
     * @param string|null $default Default value used if the key is not found.
     * @return string The sanitized string value.
     */
    public function string(string $name, ?string $default = null): string
    {
        return sanitize_text_field($this->get($name, $default));
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

        $value = strtolower(trim((string) $value));

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
        return (int) $this->get($name, $default);
    }
    #endregion

    #region Index
    /**
     * Build a flat lookup index from a nested configuration array.
     *
     * Nested arrays are recursively traversed and their leaf values are stored
     * using dot-separated keys for fast direct access.
     *
     * @param array<int|string, mixed> $data Configuration data to index.
     * @param string $prefix Current key prefix used during recursion.
     * @return void
     */
    private function buildIndex(array $data, string $prefix = ''): void
    {
        array_walk($data, function (mixed $value, int|string $key) use ($prefix): void {
            $fullKey = $prefix === ''
                ? (string)$key
                : $prefix . '.' . $key;

            if (is_array($value)) {
                $this->buildIndex($value, $fullKey);
            } else {
                $this->index[$fullKey] = $value;
            }
        });
    }

    /**
     * Resolve a configuration value from the lookup index.
     *
     * Multiple key variants are attempted to support both dot-separated and
     * underscore-separated configuration keys.
     *
     * @param string $key Configuration key to resolve.
     * @return mixed The resolved configuration value, or null if not found.
     */
    private function resolveFromIndex(string $key): mixed
    {
        $variant = array_find(
            $this->normalizeKey($key),
            fn (string $variant): bool => isset($this->index[$variant])
        );

        return $variant !== null ? $this->index[$variant] : null;
    }

    /**
     * Generate equivalent lookup keys for a configuration entry.
     *
     * Produces dot-separated and underscore-separated variants so both naming
     * conventions can be resolved transparently.
     *
     * @param string $key Original configuration key.
     * @return array<int, string> Normalized lookup key variants.
     */
    private function normalizeKey(string $key): array
    {
        return [
            $key,
            str_replace('.', '_', $key),
            str_replace('_', '.', $key),
        ];
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

                if (!isset($carry[$segment])) {
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
