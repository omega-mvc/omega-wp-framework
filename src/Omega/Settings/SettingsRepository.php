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

use function array_keys;
use function array_reduce;
use function array_reverse;
use function count;
use function end;
use function explode;
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
    /** @var array Internal settings storage containing merged defaults and persisted configuration values. */
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
     * @param array $defaults Default configuration values used as base settings before merging stored data.
     * @return void
     */
    public function __construct(protected ApplicationInterface $app, array $defaults = [])
    {
        $saved_config = get_option("{$this->app->getIdAsUnderscore()}_settings", []);
        $this->config = $this->mergeConfig($defaults, $saved_config);
    }
    #endregion

    #region Configuration Merge
    /**
     * Recursively merge two configuration arrays, preserving nested structures.
     *
     * @param array $array1 Base configuration array used as default structure.
     * @param array $array2 Stored configuration array that overrides default values.
     * @return array The merged configuration array.
     */
    private function mergeConfig(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $array_keys = array_keys($value);
                if (isset($array_keys[0]) && is_string($array_keys[0])) {
                    $merged[$key] = $this->mergeConfig($merged[$key], $value);
                } else {
                    $merged[$key] = $value;
                }
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
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
            foreach ($value as $key => $val) {
                $value[$key] = $this->processValue($val);
            }
        } elseif (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return $value;
    }
    #endregion

    #region Mutation
    /**
     * Update a configuration value and persist it to storage.
     *
     * @param mixed $name The configuration key, supports dot notation for nested values.
     * @param mixed $value The value to store, which will be processed before saving.
     * @return bool True if the update was successfully persisted, false otherwise.
     */
    public function update(mixed $name, mixed $value): bool
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
        $config = &$this->config;

        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($config[$keys[$i]]) || !is_array($config[$keys[$i]])) {
                return false;
            }
            $config = &$config[$keys[$i]];
        }

        $lastKey = end($keys);

        if (isset($config[$lastKey])) {
            unset($config[$lastKey]);
            return $this->save();
        }

        return false;
    }
    #endregion

    #region Retrieval
    /**
     * Retrieve a configuration value using dot notation.
     *
     * @param string $name The configuration key path (dot-separated).
     * @param string|null $default Default value returned if the key does not exist.
     * @return mixed The resolved configuration value or default if not found.
     */
    public function get(string $name, ?string $default = null): mixed
    {
        $names = explode('.', $name);
        $config = $this->config;

        foreach ($names as $name) {
            if (isset($config[$name])) {
                $config = $config[$name];
            } else {
                return $default;
            }
        }

        return $config;
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
        return sanitize_text_field($this->get($name, $default));
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

        return $value === 'yes' || $value === '1' || $value === 1 || $value === true;
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
        return (int)$this->get($name, $default);
    }

    /**
     * Retrieve the full configuration array.
     *
     * @return array The entire configuration set.
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
        $names = explode('.', $name);
        $config = $this->config;

        foreach ($names as $key) {
            if (!isset($config[$key])) {
                return false;
            }
            $config = $config[$key];
        }

        return true;
    }
    #endregion

    #region Helpers
    /**
     * Build a nested configuration array using a list of keys.
     *
     * @param array $keys The list of keys representing the nested path.
     * @param mixed $value The value to assign to the final key.
     * @param bool $create Whether to create missing intermediate keys (currently unused).
     * @return array The constructed nested array structure.
     * @noinspection PhpSameParameterValueInspection
     * @noinspection PhpUnusedParameterInspection
     */
    private function addKeyValueRecursively(array $keys, mixed $value, bool $create = false): array
    {
        return array_reduce(array_reverse($keys), function ($carry, $key) use ($value) {
            return [$key => $carry ?: $value];
        }, []);
    }
    #endregion
}
