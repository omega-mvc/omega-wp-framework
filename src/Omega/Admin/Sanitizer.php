<?php

/**
 * Part of Omega - Admin Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Admin;

use function array_map;
use function esc_url_raw;
use function is_array;
use function rest_sanitize_boolean;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function wp_unslash;

/**
 * Utility class for sanitizing user input using WordPress sanitization helpers.
 *
 * Provides typed sanitization methods for common scalar values and request data,
 * ensuring safe normalization of admin, REST, and form inputs.
 *
 * @category  Omega
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Sanitizer
{
    /**
     * Sanitize a boolean value.
     *
     * Accepts common truthy and falsy values such as:
     * true, false, 1, 0, 'true', 'false', 'yes', and 'no'.
     *
     * @param mixed $value Value to sanitize
     * @return bool Sanitized boolean value
     */
    public static function boolean(mixed $value): bool
    {
        return rest_sanitize_boolean($value);
    }

    /**
     * Sanitize a plain text string.
     *
     * Removes HTML tags, normalizes whitespace, and unslashes input values.
     *
     * @param mixed $value Value to sanitize
     * @param string $default Default value returned when input is null
     * @return string Sanitized string value
     */
    public static function string(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        return sanitize_text_field(wp_unslash((string)$value));
    }

    /**
     * Sanitize textarea content while preserving line breaks.
     *
     * Removes unsafe content and unslashes input values.
     *
     * @param mixed $value Value to sanitize
     * @param string $default Default value returned when input is null
     * @return string Sanitized textarea value
     */
    public static function textarea(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        return sanitize_textarea_field(wp_unslash((string)$value));
    }

    /**
     * Sanitize an integer value.
     *
     * @param mixed $value Value to sanitize
     * @param int $default Default value returned when input is empty
     * @return int Sanitized integer value
     */
    public static function integer(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (int)$value;
    }

    /**
     * Sanitize a floating-point value.
     *
     * @param mixed $value Value to sanitize
     * @param float $default Default value returned when input is empty
     * @return float Sanitized float value
     */
    public static function float(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float)$value;
    }

    /**
     * Sanitize an email address.
     *
     * @param mixed $value Value to sanitize
     * @param string $default Default value returned when sanitization fails
     * @return string Sanitized email address
     */
    public static function email(mixed $value, string $default = ''): string
    {
        $sanitized = sanitize_email((string)$value);

        return $sanitized !== '' ? $sanitized : $default;
    }

    /**
     * Sanitize a URL for storage or redirects.
     *
     * Does not escape the value for HTML output.
     *
     * @param mixed $value Value to sanitize
     * @param string $default Default value returned when sanitization fails
     * @return string Sanitized URL value
     */
    public static function url(mixed $value, string $default = ''): string
    {
        $sanitized = esc_url_raw((string)$value);

        return $sanitized !== '' ? $sanitized : $default;
    }

    /**
     * Sanitize each element of an array as a plain text string.
     *
     * Non-array values return an empty array.
     *
     * @param mixed $value Value to sanitize
     * @return array<int|string, string> Sanitized array of strings
     */
    public static function arrayOfStrings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(fn($item) => sanitize_text_field(wp_unslash((string)$item)), $value);
    }

    /**
     * Sanitize and cast a value using the specified type.
     *
     * Supported types:
     * - string
     * - boolean
     * - integer
     * - float
     * - email
     * - url
     * - textarea
     *
     * @param mixed $value Value to sanitize
     * @param string $type Sanitization type identifier
     * @param mixed $default Default value used when sanitization fails
     * @return mixed Sanitized value
     */
    public static function cast(mixed $value, string $type, mixed $default = null): mixed
    {
        return match ($type) {
            'boolean'  => static::boolean($value),
            'integer'  => static::integer($value, (int)$default),
            'float'    => static::float($value, (float)$default),
            'email'    => static::email($value, (string)$default),
            'url'      => static::url($value, (string)$default),
            'textarea' => static::textarea($value, (string)$default),
            default    => static::string($value, (string)$default),
        };
    }
}
