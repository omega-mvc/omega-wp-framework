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
 * @param mixed $response Raw response payload
 * @return mixed The payload unchanged
 */
function rest_ensure_response(mixed $response): mixed
{
    return $response;
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
