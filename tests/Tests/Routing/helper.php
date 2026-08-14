<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * Global WordPress runtime stubs. They let the Router classes run inside a
 * plain PHPUnit process by recording their calls into WordPressRuntime.
 *
 * The Router imports the WordPress API from the global namespace
 * (e.g. `use function register_rest_route;`), therefore these stubs must be
 * declared here, in the global namespace, and the PSR-4 Support classes are
 * exposed through the corresponding global class names.
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
 * @return string|false Fake admin page hook slug
 */
function add_submenu_page(mixed ...$args): string|false
{
    WordPressRuntime::$submenus[] = $args;

    return 'admin-' . count(WordPressRuntime::$submenus);
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
 * @return WPRestResponse The response instance
 */
function rest_ensure_response(mixed $response): mixed
{
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
 * @param mixed ...$args Hook registration arguments
 */
function add_action(mixed ...$args): void
{
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
