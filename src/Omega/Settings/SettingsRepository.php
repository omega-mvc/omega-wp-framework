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

namespace Omega\Settings;

use Omega\Application\ApplicationInterface;
use stdClass;

use function array_keys;
use function array_map;
use function array_pop;
use function array_reduce;
use function array_reverse;
use function array_shift;
use function count;
use function explode;
use function get_option;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sanitize_text_field;
use function update_option;

/**
 * SettingsRepository
 *
 * Provides persistent configuration storage backed by WordPress options API.
 *
 * Unlike ConfigRepository, this repository manages mutable state that is:
 * - stored in the WordPress database via update_option()
 * - loaded at runtime via get_option()
 * - merged with default configuration values
 *
 * It supports nested key access via dot notation and allows runtime mutation,
 * deletion, and persistence of settings.
 *
 * This repository is intended for:
 * - user preferences
 * - plugin settings
 * - runtime configurable options
 * service definitions, and environment-specific constants.
 *
 * @category  Omega
 * @package   Settings
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class SettingsRepository
{
    #region Properties
    /** @var array<int|string, mixed> Internal settings storage containing merged defaults and persisted configuration values. */
    protected array $config;
    #endregion

    #region Lifecycle
    /**
     * SettingsRepository handles persistent application settings stored in the WordPress options table.
     *
     * It provides methods to retrieve, update, delete, and validate configuration values,
     * supporting dot-notation access for nested arrays and automatic merging of default
     * and stored settings.
     *
     * @param ApplicationInterface $app The application instance used to resolve the option key prefix.
     * @param array<int|string, mixed> $defaults Default configuration values used as base settings before merging stored data.
     * @return void
     */
    public function __construct(protected ApplicationInterface $app, array $defaults = [])
    {
        /** @var array<int|string, mixed> $saved_config */
        $saved_config = get_option("{$this->app->getIdAsUnderscore()}_settings", []);
        $this->config = $this->mergeConfig($defaults, $saved_config);
    }
    #endregion

    #region Configuration Merge
    /**
     * Recursively merge two configuration arrays, preserving nested structures.
     *
     * @param array<int|string, mixed> $array1 Base configuration array used as default structure.
     * @param array<int|string, mixed> $array2 Stored configuration array that overrides default values.
     * @return array<int|string, mixed> The merged configuration array.
     */
    private function mergeConfig(array $array1, array $array2): array
    {
        return array_reduce(
            array_keys($array2),
            function (array $merged, string|int $key) use ($array2): array {
                $value = $array2[$key];

                if (!is_array($value)) {
                    $merged[$key] = $value;

                    return $merged;
                }

                if (!isset($merged[$key])) {
                    $merged[$key] = $value;

                    return $merged;
                }

                if (!is_array($merged[$key])) {
                    $merged[$key] = $value;

                    return $merged;
                }

                $arrayKeys = array_keys($value);

                if (isset($arrayKeys[0])) {
                    if (is_string($arrayKeys[0])) {
                        $merged[$key] = $this->mergeConfig($merged[$key], $value);
                    } else {
                        $merged[$key] = $value;
                    }
                } else {
                    $merged[$key] = $value;
                }

                return $merged;
            },
            $array1
        );
    }
    #endregion

    #region Persistence
    /**
     * Persist the current configuration to the database.
     *
     * @return bool True if the configuration was successfully saved, false otherwise.
     */
    private function save(): bool
    {
        return update_option("{$this->app->getIdAsUnderscore()}_settings", $this->config);
    }
    #endregion

    #region Processing
    /**
     * Normalize a value before storing it in the configuration.
     *
     * Converts boolean values into storage-safe string representations and
     * recursively processes nested arrays.
     *
     * @param mixed $value The value to process before storage.
     * @return mixed The normalized value ready for persistence.
     */
    private function processValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $val): mixed => $this->processValue($val), $value);
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return $value;
    }
    #endregion

    #region Mutation
    /**
     * Update a configuration value and persist it to storage.
     *
     * @param string $name The configuration key, supports dot notation for nested values.
     * @param mixed $value The value to store, which will be processed before saving.
     * @return bool True if the update was successfully persisted, false otherwise.
     */
    public function update(string $name, mixed $value): bool
    {
        $processed_value = $this->processValue($value);

        $keys = explode('.', $name);

        $update_data = count($keys) > 1
            ? $this->addKeyValueRecursively($keys, $processed_value)
            : [$name => $processed_value];

        $settings = $this->mergeConfig($this->config, $update_data);

        $this->config = $settings;

        return $this->save();
    }

    /**
     * Delete a configuration key using dot notation.
     *
     * @param string $name The configuration key path to remove.
     * @return bool True if the key was successfully deleted and saved, false otherwise.
     */
    public function delete(string $name): bool
    {
        $keys = explode('.', $name);
        $lastKey = array_pop($keys);

        return $this->removeKey($this->config, $keys, $lastKey);
    }

    /**
     * Recursively remove a key from the configuration using a list of parent segments.
     *
     * @param array<int|string, mixed> $config The configuration level to mutate, passed by reference.
     * @param list<int|string> $keys Remaining parent key segments leading to the target key.
     * @param string $lastKey The final key segment to remove.
     * @return bool True if the key was removed and saved, false otherwise.
     */
    private function removeKey(array &$config, array $keys, string $lastKey): bool
    {
        if ($keys === []) {
            if (!isset($config[$lastKey])) {
                return false;
            }

            unset($config[$lastKey]);

            return $this->save();
        }

        $firstKey = array_shift($keys);

        if (!isset($config[$firstKey])) {
            return false;
        }

        if (!is_array($config[$firstKey])) {
            return false;
        }

        return $this->removeKey($config[$firstKey], $keys, $lastKey);
    }
    #endregion

    #region Retrieval
    /**
     * Retrieve a configuration value using dot notation.
     *
     * @param string $name The configuration key path (dot-separated).
     * @param mixed $default Default value returned if the key does not exist.
     * @return mixed The resolved configuration value or default if not found.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $marker = new stdClass();

        $result = array_reduce(
            explode('.', $name),
            function (mixed $carry, string $segment) use ($marker): mixed {
                if ($carry === $marker) {
                    return $marker;
                }

                if (!is_array($carry)) {
                    return $marker;
                }

                if (!isset($carry[$segment])) {
                    return $marker;
                }

                return $carry[$segment];
            },
            $this->config
        );

        return $result === $marker ? $default : $result;
    }

    /**
     * Retrieve a configuration value as a sanitized string.
     *
     * @param string $name The configuration key path.
     * @param string|null $default Default value if the key is missing.
     * @return string The sanitized string value.
     */
    public function string(string $name, ?string $default = null): string
    {
        $value = $this->get($name, $default);

        if (!is_string($value)) {
            return sanitize_text_field((string) ($default ?? ''));
        }

        return sanitize_text_field($value);
    }

    /**
     * Retrieve a configuration value as a boolean.
     *
     * @param string $name The configuration key path.
     * @param bool|string $default Default value used if the key is missing.
     * @return bool The resolved boolean value.
     */
    public function boolean(string $name, bool|string $default = false): bool
    {
        $value = $this->get($name, $default);

        if (is_bool($value)) {
            return $value;
        }

        $isTruthy = in_array($value, ['yes', '1', 1], true);

        return $isTruthy;
    }

    /**
     * Retrieve a configuration value as an integer.
     *
     * @param string $name The configuration key path.
     * @param int|null $default Default value if the key is missing.
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

    /**
     * Retrieve the full configuration array.
     *
     * @return array<int|string, mixed> The entire configuration set.
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * Determine whether a configuration key exists.
     *
     * @param string $name The configuration key path to check.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $name): bool
    {
        return $this->get($name, null) !== null;
    }
    #endregion

    #region Helpers
    /**
     * Build a nested configuration array using a list of keys.
     *
     * @param list<int|string> $keys The list of keys representing the nested path.
     * @param mixed $value The value to assign to the final key.
     * @return array<int|string, mixed> The constructed nested array structure.
     */
    private function addKeyValueRecursively(array $keys, mixed $value): array
    {
        /** @var array<int|string, mixed> $result */
        $result = array_reduce(array_reverse($keys), function (array $carry, int|string $key) use ($value): array {
            return [$key => $carry !== [] ? $carry : $value];
        }, []);

        return $result;
    }
    #endregion
}
