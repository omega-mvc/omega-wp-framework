<?php

/**
 * Part of Omega - Environment Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Omega\Environment;

use Dotenv\Dotenv;

use function array_key_exists;
use function is_numeric;
use function strtolower;

/**
 * Env class for loading and accessing environment variables.
 *
 * This class allows loading environment variables from a file and provides
 * a convenient way to retrieve them with automatic type casting for common values.
 *
 * @category  Omega
 * @package   Environment
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
class Env
{
    /**
     * @var array<string, mixed> Stores the loaded environment variables.
     */
    protected static array $values = [];

    /**
     * Load environment variables from a given path and file.
     *
     * @param string $path The directory path containing the environment file.
     * @param string $file The environment filename, defaults to '.env'.
     * @return void
     */
    public static function load(string $path, string $file = '.env'): void
    {
        $dotenv = Dotenv::createImmutable($path, $file);
        self::$values = $dotenv->load();
    }

    /**
     * Retrieve an environment variable by key with optional default value.
     *
     * Automatically converts string values to proper types:
     * - "true" or "(true)" => true
     * - "false" or "(false)" => false
     * - "null" or "(null)" => null
     * - "empty" or "(empty)" => empty string
     * - numeric strings => integers or floats
     *
     * @param string $key The environment variable key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The value of the environment variable, cast if applicable, or $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Chiamata corretta al metodo statico
        $value = self::resolveValue($key, $default);

        return is_string($value) ? self::cast($value) : $value;
    }

    /**
     * Resolve the raw environment value for the given key.
     *
     * The method first checks the internally loaded environment variables.
     * If the key is not present, it attempts to retrieve the value from the
     * system environment using getenv(). If the key cannot be found in either
     * location, the provided default value is returned.
     *
     * This method does not perform any type casting; it only resolves the
     * raw value source.
     *
     * @param string $key The environment variable name to resolve.
     * @param mixed $default The default value returned when the variable
     *                       is not defined in the loaded values or system environment.
     * @return mixed The raw environment value if found, otherwise the default value.
     */
    private static function resolveValue(string $key, mixed $default): mixed
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $envValue = getenv($key);

        return ($envValue !== false) ? $envValue : $default;
    }

    /**
     * Cast a string environment value to its appropriate PHP type.
     *
     * This method converts commonly used string representations into their
     * corresponding native PHP types:
     *
     * - "true"  → true
     * - "false" → false
     * - "null"  → null
     * - "empty" → empty string
     *
     * If the value does not match any of these special keywords, it will be
     * passed to castNumeric() to determine whether it represents a numeric
     * value. Otherwise the original string will be returned unchanged.
     *
     * Additionally, common typographical mistakes such as "tru", "flase",
     * or "nul" will trigger an exception to prevent silent misconfiguration
     * in environment files.
     *
     * @param string $value The raw string value retrieved from the environment.
     * @return mixed The value converted to its corresponding PHP type, or the
     *               original string if no conversion rule applies.
     */
    private static function cast(string $value): mixed
    {
        $lower = strtolower($value);

        $specialValues = [
            'true'  => true,
            'false' => false,
            'null'  => null,
            'empty' => '',
        ];

        if (array_key_exists($lower, $specialValues)) {
            return $specialValues[$lower];
        }

        return self::castNumeric($value);
    }

    /**
     * Cast numeric string values to their corresponding PHP numeric type.
     *
     * If the provided value is a numeric string, it will be converted into
     * either an integer or a float depending on its format. Non-numeric
     * strings are returned unchanged.
     *
     * The numeric conversion is performed using PHP's implicit numeric
     * casting by adding zero to the value.
     *
     * @param string $value The string value to evaluate.
     * @return mixed An integer or float if the value is numeric, otherwise
     *               the original string.
     */
    private static function castNumeric(string $value): mixed
    {
        return is_numeric($value) ? $value + 0 : $value;
    }
}
