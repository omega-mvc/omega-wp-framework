<?php

/**
 * Part of Omega - Str Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Str;

use function array_key_exists;
use function array_reduce;
use function count;
use function explode;
use function is_array;
use function str_contains;
use function str_replace;
use function strncmp;

/**
 * String and array utility helper with dot-notation support.
 *
 * This class provides lightweight helpers for working with arrays
 * and strings in a structured way, inspired by framework-style
 * utility classes.
 *
 * It includes:
 * - Nested array access using dot notation
 * - Nested array mutation using dot notation
 * - Basic string utilities used across the framework
 *
 * Example usage:
 *
 * $data = [
 *     'user' => [
 *         'email' => 'test@example.com'
 *     ]
 * ];
 *
 * $email = Str::getNestedValue($data, 'user.email');
 *
 * Str::setNestedValue($data, 'user.name', 'John');
 *
 * ------------------------------------------------------------
 * DESIGN NOTES
 * ------------------------------------------------------------
 *
 * This class is intentionally stateless and static-only.
 * It is meant to be used as a low-level utility layer across
 * the framework (Validator, Models, etc.).
 *
 * It does not perform validation or type coercion.
 *
 * @category  Omega
 * @package   Str
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Str
{
    #region Array Helpers
    /**
     * Retrieve a value from an array using dot notation.
     *
     * Supports deep access without requiring manual traversal.
     *
     * Example:
     * 'user.profile.email'
     *
     * @param array<int|string, mixed> $data The source array     * @param string $key Dot-notation key path
     * @param mixed|null $default Default value if key does not exist
     * @return mixed Returns found value or default
     */
    public static function getNestedValue(array $data, string $key, mixed $default = null): mixed
    {
        if (!str_contains($key, '.')) {
            return $data[$key] ?? $default;
        }

        $keys   = explode('.', $key);
        $value  = $data;
        $exists = true;

        array_walk($keys, function (string $segment) use (&$value, &$exists): void {
            if (!$exists) {
                return;
            }

            if (!is_array($value)) {
                $exists = false;
                return;
            }

            if (!array_key_exists($segment, $value)) {
                $exists = false;
                return;
            }

            $value = $value[$segment];
        });

        return $exists ? $value : $default;
    }

    /**
     * Set a value in an array using dot notation.
     *
     * Automatically creates intermediate array levels if they do not exist.
     *
     * Example:
     * 'user.profile.email'
     *
     * @param array<int|string, mixed> $data The target array (by reference)
     * @param string $key Dot-notation key path
     * @param mixed $value Value to assign
     * @return void
     */
    public static function setNestedValue(array &$data, string $key, mixed $value): void
    {
        if (!str_contains($key, '.')) {
            $data[$key] = $value;
            return;
        }

        $keys   = array_reverse(explode('.', $key));
        $nested = (array) array_reduce(
            $keys,
            static fn(mixed $carry, string $segment): array => [$segment => $carry],
            $value
        );

        $data = array_replace_recursive($data, $nested);
    }
    #endregion

    #region String Helpers
    /**
     * Convert a string to snake-case format.
     *
     * This implementation currently replaces hyphens with underscores.
     *
     * Example:
     * "user-name" → "user_name"
     *
     * @param string $string Input string
     * @return string Converted string in snake_case format
     */
    public static function toSnake(string $string): string
    {
        return str_replace('-', '_', $string);
    }

    /**
     * Determine whether a string starts with a given prefix.
     *
     * This is a lightweight, binary-safe implementation using strncmp.
     *
     * @param string $haystack The full string to check
     * @param string $needle The prefix to search for
     * @return bool True if haystack starts with needle, otherwise false
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
    #endregion
}
