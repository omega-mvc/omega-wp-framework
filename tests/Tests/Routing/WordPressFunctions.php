<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * Global WordPress runtime doubles. They let the Router classes run inside a
 * plain PHPUnit process by recording their calls into WordPressRuntime.
 *
 * The Router imports the WordPress API from the global namespace
 * (e.g. `use function register_rest_route;`), therefore these doubles must be
 * declared here, in the global namespace, and the PSR-4 Support classes are
 * exposed through the corresponding global class names.
 *
 * This file is required by tests/bootstrap.php and never by the Composer
 * autoloader: `php-stubs/wordpress-stubs`, which
 * `szepeviktor/phpstan-wordpress` loads as a PHPStan bootstrap file, declares
 * the very same global functions and would abort with "Cannot redeclare
 * function ..." if they were already declared while the autoloader boots.
 * tests/bootstrap.php therefore only requires this file when the real
 * WordPress API is absent.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

use Tests\Routing\Support\WPError;
use Tests\Routing\Support\WPRestResponse;
use Tests\Routing\Support\WPTheme;
use Tests\Routing\WordPressRuntime;

/**
 * Stub for register_rest_route().
 *
 * Records the call and returns success.
 *
 * @param string               $namespace REST route namespace
 * @param string               $route     REST route pattern
 * @param array<string, mixed> $args      Route arguments
 *
 * @return bool Always true
 */
function register_rest_route(string $namespace, string $route, array $args = []): bool
{
    WordPressRuntime::$restRoutes[] = [$namespace, $route, $args];

    return true;
}

/**
 * Stub for add_menu_page().
 *
 * Records the call and returns a fake menu slug.
 *
 * @param mixed ...$args Positional arguments forwarded by WordPress
 * @return string Fake menu hook slug
 */
function add_menu_page(mixed ...$args): string
{
    WordPressRuntime::$menus[] = $args;

    return 'menu-' . count(WordPressRuntime::$menus);
}

/**
 * Stub for add_submenu_page().
 *
 * Records the call and returns a fake admin page hook slug.
 *
 * @param mixed ...$args Positional arguments forwarded by WordPress
 * @return string Fake admin page hook slug
 */
function add_submenu_page(mixed ...$args): string
{
    WordPressRuntime::$submenus[] = $args;

    return 'admin-' . count(WordPressRuntime::$submenus);
}

/**
 * Stub for remove_submenu_page().
 *
 * Records the call and returns.
 *
 * @param mixed ...$args Positional arguments forwarded by WordPress
 * @return void
 */
function remove_submenu_page(mixed ...$args): void
{
    WordPressRuntime::$removedSubmenus[] = $args;
}

/**
 * Stub for current_user_can().
 *
 * @param string $capability Capability being checked
 * @return bool Result controlled by WordPressRuntime::$capabilities
 */
function current_user_can(string $capability): bool
{
    return WordPressRuntime::$capabilities;
}

/**
 * Stub for rest_ensure_response().
 *
 * Wraps raw payloads into a WPRestResponse instance, mirroring WordPress,
 * and passes already-built responses through unchanged.
 *
 * @param mixed $response Raw response payload
 * @return WPRestResponse|WPError The response instance
 */
function rest_ensure_response(mixed $response): mixed
{
    if (WordPressRuntime::$forceRestError) {
        return new WPError('rest_error', 'REST Error', ['status' => 500]);
    }

    if ($response instanceof WPRestResponse) {
        return $response;
    }

    return new WPRestResponse($response);
}

/**
 * Stub for is_wp_error().
 *
 * @param mixed $thing Value being inspected
 * @return bool Whether the value is a WPError instance
 */
function is_wp_error(mixed $thing): bool
{
    return $thing instanceof WPError;
}

/**
 * Stub for esc_html().
 *
 * @param string $text Raw text
 * @return string HTML-escaped text
 */
function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES);
}

/**
 * Stub for add_action().
 *
 * Records the call in the runtime registry and returns.
 *
 * @param mixed ...$args Hook registration arguments
 */
function add_action(mixed ...$args): void
{
    WordPressRuntime::$actions[] = $args;
}

/**
 * Stub for load_plugin_textdomain().
 *
 * Records the call in the runtime registry.
 *
 * @param string      $domain       Text domain to load
 * @param string|false $deprecated   Unused legacy argument
 * @param string|false $pluginRelPath Path relative to WP_PLUGIN_DIR
 * @return bool Always true
 */
function load_plugin_textdomain(
    string $domain,
    string|false $deprecated = false,
    string|false $pluginRelPath = false
): bool {
    WordPressRuntime::$textdomains[] = ['plugin', $domain, $deprecated, $pluginRelPath];

    return true;
}

/**
 * Stub for load_theme_textdomain().
 *
 * Records the call in the runtime registry.
 *
 * @param string       $domain Text domain to load
 * @param string|false $path   Absolute path to the languages directory
 * @return bool Always true
 */
function load_theme_textdomain(string $domain, string|false $path = false): bool
{
    WordPressRuntime::$textdomains[] = ['theme', $domain, $path];

    return true;
}

/**
 * Stub for add_filter().
 *
 * Records the call and returns.
 *
 * @param mixed ...$args Filter registration arguments
 */
function add_filter(mixed ...$args): void
{
    WordPressRuntime::$filters[] = $args;
}

if (!function_exists('apply_filters')) {
    /**
     * Stub for apply_filters().
     *
     * Applies every callback registered for the hook in registration order and
     * records the call, so tests can observe a filter rewriting a value without
     * booting the real WordPress hook system.
     *
     * @param string $hook  Filter hook name.
     * @param mixed  $value Value being filtered.
     * @param mixed  ...$args Additional arguments passed to the callbacks.
     * @return mixed The filtered value.
     */
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        Tests\Routing\WordPressRuntime::$appliedFilters[] = [$hook, $value, array_values($args)];

        foreach (Tests\Routing\WordPressRuntime::$filters as $filter) {
            if (($filter[0] ?? null) !== $hook) {
                continue;
            }

            $callback = $filter[1] ?? null;

            if (is_callable($callback)) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }
}

/**
 * Stub for sanitize_text_field().
 *
 * Strips tags and trims the input to keep values predictable in tests.
 *
 * @param string $text Raw text
 * @return string Sanitized text
 */
function sanitize_text_field(string $text): string
{
    return trim(strip_tags($text));
}

/**
 * Stub for get_option().
 *
 * @param string $name   Option name
 * @param mixed  $default Default value when the option is missing
 * @return mixed The option value or the default
 */
function get_option(string $name, mixed $default = false): mixed
{
    return WordPressRuntime::$options[$name] ?? $default;
}

/**
 * Stub for update_option().
 *
 * Stores the value in the runtime registry and returns success.
 *
 * @param string $name     Option name
 * @param mixed  $value    Option value
 * @param bool   $autoload Whether the option should be autoloaded
 * @return bool Always true
 */
function update_option(string $name, mixed $value, bool $autoload = true): bool
{
    WordPressRuntime::$options[$name] = $value;
    WordPressRuntime::$optionUpdates[] = [$name, $value, $autoload];

    return true;
}

/**
 * Stub for get_file_data().
 *
 * @param string        $file           Plugin file path
 * @param array<string, mixed> $defaultHeaders Headers to read
 * @param string        $context        File context
 * @return array<string, string> Header values from the runtime registry
 */
function get_file_data(string $file, array $defaultHeaders, string $context = 'plugin'): array
{
    $result = [];

    foreach ($defaultHeaders as $field => $regex) {
        $result[$field] = WordPressRuntime::$fileHeaders[$field] ?? '';
    }

    return $result;
}

/**
 * Stub for wp_get_theme().
 *
 * @param string|null $stylesheet Theme directory name
 * @param string      $themeRoot  Absolute path to themes directory
 * @return WPTheme Theme instance from the runtime registry
 */
function wp_get_theme(?string $stylesheet = null, string $themeRoot = ''): WPTheme
{
    return WordPressRuntime::$theme ?? new WPTheme([]);
}

/**
 * Stub for current_time().
 *
 * Returns a fixed MySQL timestamp so soft deletes and timestamps stay
 * deterministic across test runs.
 *
 * @param string   $type Format of the returned value ('mysql' or 'timestamp')
 * @param int|bool $gmt  Unused GMT flag kept for signature parity
 * @return int|string A fixed timestamp in the requested format
 */
function current_time(string $type = 'timestamp', int|bool $gmt = 0): int|string
{
    return $type === 'mysql' ? '2026-01-01 00:00:00' : 1735689600;
}

/**
 * Stub for dbDelta().
 *
 * Records the DDL statements it receives so schema creation stays
 * observable without a real WordPress upgrade environment.
 *
 * @param string|array<int, string> $queries CREATE TABLE statement(s) to apply
 * @return array<int, string> The recorded statements
 */
function dbDelta(string|array $queries): array
{
    $statements = is_array($queries) ? $queries : [$queries];

    WordPressRuntime::$dbDeltaStatements = array_merge(WordPressRuntime::$dbDeltaStatements, $statements);

    return $statements;
}

/**
 * Stub for wp_json_encode().
 *
 * Mirrors the WordPress helper by delegating to the native encoder, so the
 * ORM casters can be exercised without the full WordPress runtime.
 *
 * @param mixed $data    Value to encode.
 * @param int   $options Encoding options, forwarded to json_encode().
 * @param int   $depth   Maximum nesting depth, forwarded to json_encode().
 * @return string|false The encoded string, or false when encoding fails.
 */
function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
{
    return json_encode($data, $options, max(1, $depth));
}

/**
 * Stub for wp_list_pluck().
 *
 * Mirrors the WordPress helper by pulling a single field out of every row,
 * so the eager loading queries can be exercised without the full WordPress
 * runtime.
 *
 * @param array<int|string, mixed> $list      Rows to pluck from.
 * @param string|int               $field     Row key to read.
 * @param string|int|null          $indexKey  Optional key the plucked values are indexed by.
 * @return array<int|string, mixed> The plucked values.
 */
function wp_list_pluck(array $list, string|int $field, string|int|null $indexKey = null): array
{
    $plucked = [];

    foreach ($list as $row) {
        $row = (array) $row;
        $key = $row[$field] ?? null;
        $id  = $indexKey === null ? null : ($row[$indexKey] ?? null);

        if (!is_int($id) && !is_string($id)) {
            $plucked[] = $key;
            continue;
        }

        $plucked[$id] = $key;
    }

    return $plucked;
}

/**
 * Stub for esc_sql().
 *
 * Escapes quotes and backslashes so schema statements generated by the
 * blueprint builder stay safe to interpolate.
 *
 * @param mixed $data Value to escape
 * @return string|array<int|string, string> The escaped value
 */
function esc_sql(mixed $data): string|array
{
    if (is_array($data)) {
        return array_map(
            static function (mixed $item): string {
                $escaped = esc_sql($item);

                return is_string($escaped) ? $escaped : '';
            },
            $data
        );
    }

    return addslashes(is_scalar($data) ? (string) $data : '');
}

if (!function_exists('did_action')) {
    /**
     * Stub for did_action().
     *
     * Reports how many times a hook has already been dispatched, reading the
     * registry the tests populate through WordPressRuntime::$firedActions.
     *
     * @param string $hook Hook name to query.
     * @return int Number of times the hook has already fired, 0 when never.
     */
    function did_action(string $hook): int
    {
        return Tests\Routing\WordPressRuntime::$firedActions[$hook] ?? 0;
    }
}

if (!function_exists('has_action')) {
    /**
     * Stub for has_action().
     */
    function has_action(mixed $hook, mixed $callback = null): int|bool
    {
        $found = false;

        foreach (Tests\Routing\WordPressRuntime::$actions as $action) {
            if ($action[0] !== $hook) {
                continue;
            }
            if ($callback === null) {
                $found = true;
                break;
            }
            if (($action[1] ?? null) === $callback) {
                $found = true;
                break;
            }
        }

        if ($found === false) {
            return false;
        }

        if ($callback === null) {
            return true;
        }

        $priority = $action[2] ?? 10;

        return is_numeric($priority) ? (int) $priority : 10;
    }
}


if (!function_exists('remove_all_actions')) {
    /**
     * Stub for remove_all_actions().
     */
    function remove_all_actions(mixed $hook): void
    {
        Tests\Routing\WordPressRuntime::$actions = array_values(array_filter(
            Tests\Routing\WordPressRuntime::$actions,
            fn (array $action): bool => $action[0] !== $hook
        ));
    }
}


if (!function_exists('remove_action')) {
    /**
     * Stub for remove_action().
     */
    function remove_action(mixed $hook, mixed $callback, int $priority = 10): void
    {
        Tests\Routing\WordPressRuntime::$actions = array_values(array_filter(
            Tests\Routing\WordPressRuntime::$actions,
            fn (array $action): bool => !($action[0] === $hook && ($action[1] ?? null) === $callback)
        ));
    }
}


if (!function_exists('wp_create_nonce')) {
    /**
     * Stub for wp_create_nonce().
     */
    function wp_create_nonce(string $action = ''): string
    {
        return 'nonce-' . md5($action);
    }
}


if (!function_exists('wp_verify_nonce')) {
    /**
     * Stub for wp_verify_nonce().
     */
    function wp_verify_nonce(mixed $nonce, string $action = ''): bool
    {
        return true;
    }
}


if (!function_exists('wp_unslash')) {
    /**
     * Stub for wp_unslash().
     */
    function wp_unslash(string $value): string
    {
        return stripslashes($value);
    }
}


if (!function_exists('esc_url_raw')) {
    /**
     * Stub for esc_url_raw().
     */
    function esc_url_raw(string $url): string
    {
        if (strpos($url, 'javascript:') === 0) {
            return '';
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        return $url;
    }
}


if (!function_exists('sanitize_email')) {
    /**
     * Stub for sanitize_email().
     */
    function sanitize_email(string $email): string
    {
        $san = (string) filter_var($email, FILTER_SANITIZE_EMAIL);
        if (filter_var($san, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }
        return $san;
    }
}


if (!function_exists('sanitize_textarea_field')) {
    /**
     * Stub for sanitize_textarea_field().
     */
    function sanitize_textarea_field(string $str): string
    {
        return strip_tags($str);
    }
}


if (!function_exists('rest_sanitize_boolean')) {
    /**
     * Stub for rest_sanitize_boolean().
     */
    function rest_sanitize_boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        return false;
    }
}


if (!function_exists('__')) {
    /**
     * Stub for __().
     */
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
