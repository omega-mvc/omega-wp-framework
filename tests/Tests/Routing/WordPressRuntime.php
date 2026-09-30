<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Routing;

use Tests\Routing\Support\WPTheme;

/**
 * In-memory registry for the WordPress runtime stubs used by the routing tests.
 *
 * Every stub records its invocation arguments here so tests can assert on the
 * exact calls performed by the Router without booting WordPress.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class WordPressRuntime
{
    /**
     * Recorded calls to register_rest_route(): [namespace, route, args].
     *
     * @var list<array{0:string,1:string,2:array<string, mixed>}>
     */
    public static array $restRoutes = [];

    /**
     * Recorded calls to add_menu_page(): list of positional argument arrays.
     *
     * @var list<array<int|string, mixed>>
     */
    public static array $menus = [];

    /**
     * Recorded calls to add_submenu_page(): list of positional argument arrays.
     *
     * @var list<array<int|string, mixed>>
     */
    public static array $submenus = [];

    /**
     * Capability check result returned by the current_user_can() stub.
     */
    public static bool $capabilities = true;

    /**
     * Option values returned by the get_option() stub, keyed by option name.
     *
     * @var array<string, mixed>
     */
    public static array $options = [];

    /**
     * DDL statements recorded by the dbDelta() stub.
     *
     * @var list<string>
     */
    public static array $dbDeltaStatements = [];

    /**
     * Recorded calls to update_option(): [name, value, autoload].
     *
     * @var list<array{0:string,1:mixed,2:bool}>
     */
    public static array $optionUpdates = [];

    /**
     * Plugin header values returned by the get_file_data() stub, keyed by header.
     *
     * @var array<string, string>
     */
    public static array $fileHeaders = [];

    /**
     * Theme instance returned by the wp_get_theme() stub.
     */
    public static ?WPTheme $theme = null;

    /**
     * Recorded calls to add_filter(): list of positional argument arrays.
     *
     * @var list<array<int|string, mixed>>
     */
    public static array $filters = [];

    /**
     * Recorded calls to add_action(): list of positional argument arrays.
     *
     * @var list<array<int|string, mixed>>
     */
    public static array $actions = [];

    /**
     * Recorded calls to load_plugin_textdomain() and load_theme_textdomain():
     * list of positional argument arrays.
     *
     * @var list<array<int|string, mixed>>
     */
    public static array $textdomains = [];

    /**
     * When true, the rest_ensure_response() stub returns a WP_Error instead
     * of wrapping the payload in a WPRestResponse.
     */
    public static bool $forceRestError = false;

    /**
     * Returns the callable stored at a key of the first registered REST route
     * args, e.g. "callback" or "permission_callback".
     *
     * The recorded args are typed as array<string, mixed>, so the concrete
     * callable is only known at runtime. The helper narrows it once here
     * instead of scattering is_callable() checks across every test.
     *
     * @param string $key      Key inside the recorded route args.
     * @return callable The stored callable.
     *
     * @throws \RuntimeException When the key is missing or is not callable.
     */
    public static function restRouteCallable(string $key = 'callback'): callable
    {
        $callable = self::$restRoutes[0][2][$key] ?? null;

        if (!is_callable($callable)) {
            throw new \RuntimeException(sprintf(
                'No callable "%s" was recorded on the first REST route.',
                $key
            ));
        }

        return $callable;
    }

    /**
     * Returns the admin menu callback WordPress received as argument 5 of the
     * first add_submenu_page() call.
     *
     * @return callable The stored admin menu callback.
     *
     * @throws \RuntimeException When no callable was recorded.
     */
    public static function firstSubmenuCallback(): callable
    {
        $callable = self::$submenus[0][5] ?? null;

        if (!is_callable($callable)) {
            throw new \RuntimeException('No admin menu callback was recorded on add_submenu_page().');
        }

        return $callable;
    }

    /**
     * Returns the callback WordPress received for the first add_action()
     * call registered under the given hook name.
     *
     * @param string $hookName WordPress hook name, e.g. 'rest_api_init'.
     * @return callable The stored hook callback.
     *
     * @throws \RuntimeException When no callable was recorded for the hook.
     */
    public static function firstActionCallback(string $hookName): callable
    {
        foreach (self::$actions as $action) {
            if ($action[0] === $hookName && is_callable($action[1] ?? null)) {
                return $action[1];
            }
        }

        throw new \RuntimeException(sprintf('No callback was recorded for the "%s" hook.', $hookName));
    }

    /**
     * Resets every registry value between tests.
     */
    public static function reset(): void
    {
        self::$restRoutes = [];
        self::$menus = [];
        self::$submenus = [];
        self::$capabilities = true;
        self::$options = [];
        self::$dbDeltaStatements = [];
        self::$optionUpdates = [];
        self::$fileHeaders = [];
        self::$theme = null;
        self::$filters = [];
        self::$actions = [];
        self::$textdomains = [];
        self::$forceRestError = false;
    }
}
