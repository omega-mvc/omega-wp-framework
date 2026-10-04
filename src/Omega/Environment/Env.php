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
use function array_merge;
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
     * Values are merged into the already-loaded store instead of replacing it,
     * so calling this method more than once in the same process is safe:
     * previously loaded keys are preserved (re-loading the same file only adds
     * keys that are not already present, matching the immutable Dotenv loader).
     *
     * @param string $path The directory path containing the environment file.
     * @param string $file The environment filename, defaults to '.env'.
     * @return void
     */
    public static function load(string $path, string $file = '.env'): void
    {
        $dotenv = Dotenv::createImmutable($path, $file);
        self::$values = array_merge(self::$values, $dotenv->load());
    }

    /**
     * Retrieve an environment variable by key with optional default value.
     *
     * A value read from the loaded store or from the process environment is
     * converted to its native PHP type:
     * - "true" => true
     * - "false" => false
     * - "null" => null
     * - "empty" => empty string
     * - numeric strings => integers or floats
     *
     * The default is returned verbatim and is never converted: it describes
     * what the caller wants when the key is in no source, not something read
     * from a configuration file.
     *
     * @param string $key The environment variable key to retrieve.
     * @param mixed $default The value to return when the key is in no source.
     * @return mixed The cast environment value, or the untouched default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$value, $fromSource] = self::resolveValue($key, $default);

        return ($fromSource && is_string($value)) ? self::cast($value) : $value;
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
     * raw value source and reports which of the two it took.
     *
     * @param string $key The environment variable name to resolve.
     * @param mixed $default The value returned when the variable is not
     *                       defined in the loaded values or system environment.
     * @return array{0: mixed, 1: bool} The resolved value, and whether it came from a source.
     */
    private static function resolveValue(string $key, mixed $default): array
    {
        if (array_key_exists($key, self::$values)) {
            return [self::$values[$key], true];
        }

        $envValue = getenv($key);

        if ($envValue !== false) {
            return [$envValue, true];
        }

        return [$default, false];
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
     * A misspelled keyword is not detected and is returned unchanged, like
     * any other unrecognised value.
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
