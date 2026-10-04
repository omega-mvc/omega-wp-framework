<?php

/**
 * Part of Omega - Tests Admin Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Admin\Fixtures;

use Omega\Admin\Menu\AbstractMenuBuilder;

/**
 * Menu builder declaring a single top-level menu with two submenus.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class SampleAdminMenu extends AbstractMenuBuilder
{
    /**
     * Base paths of the applications the menu has been built with.
     *
     * @var array<int, string>
     */
    public static array $registeredBasePaths = [];

    /**
     * Declare a menu with an explicit icon, capability and position.
     *
     * @return void
     */
    public function register(): void
    {
        self::$registeredBasePaths[] = $this->app->getBasePath();

        $menu = $this->add('omega-tasks', 'Tasks')
            ->icon('dashicons-lightbulb')
            ->capability('edit_posts')
            ->position(58);

        $menu->addSubmenu('All Tasks', 'omega-tasks-all');
        $menu->addSubmenu('Archived Tasks');
    }

    /**
     * Reset the recorded base paths.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$registeredBasePaths = [];
    }
}
