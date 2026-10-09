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

namespace Omega\Admin\Menu;

/**
 * Registry of admin page slugs already handed to WordPress.
 *
 * Both the menu builder and the Router register admin submenu pages under
 * their own slug. Registering the same slug through both systems would hand
 * WordPress two menu entries with different callbacks. This registry makes
 * sure a slug is handed to WordPress only once per request.
 *
 * @category   Omega
 * @package    Admin
 * @subpackage Menu
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
final class AdminPageRegistry
{
    /** @var array<string, true> Admin page slugs already registered. */
    private static array $slugs = [];

    /**
     * Register a slug unless it has already been registered.
     *
     * @param string $slug Admin page slug.
     * @return bool True when the slug was newly registered, false when already present.
     */
    public static function register(string $slug): bool
    {
        if (isset(self::$slugs[$slug])) {
            return false;
        }

        self::$slugs[$slug] = true;

        return true;
    }

    /**
     * Forget every registered slug.
     *
     * Used by the test suites to isolate test cases from one another.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$slugs = [];
    }
}
