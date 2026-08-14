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
     * @var list<array<int, mixed>>
     */
    public static array $menus = [];

    /**
     * Recorded calls to add_submenu_page(): list of positional argument arrays.
     *
     * @var list<array<int, mixed>>
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
     * @var list<array<int, mixed>>
     */
    public static array $filters = [];

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
        self::$optionUpdates = [];
        self::$fileHeaders = [];
        self::$theme = null;
        self::$filters = [];
    }
}
