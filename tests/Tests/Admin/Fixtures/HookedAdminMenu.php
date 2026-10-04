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
 * Menu builder declaring a menu through the builder level submenu helper.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class HookedAdminMenu extends AbstractMenuBuilder
{
    /**
     * Declare a top-level menu and hook a submenu on the admin_menu action.
     *
     * @return void
     */
    public function register(): void
    {
        $this->add('omega-hooks', 'Hooks');

        $this->addSubmenu('omega-hooks', 'omega-hooks-child', 'Child', 25)
            ->capability('read')
            ->setCallback(static function (): void {
                // Rendering is handled by the routing layer in the real application.
            });
    }
}
