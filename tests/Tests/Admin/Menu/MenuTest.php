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

namespace Tests\Admin\Menu;

use Omega\Admin\Menu\Menu;
use Omega\Admin\Menu\Submenu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Test the Menu class.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Menu::class)]
final class MenuTest extends TestCase
{
    /**
     * Test a fresh menu has no submenu.
     *
     * @return void
     */
    public function testAFreshMenuHasNoSubmenu(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $this->assertSame([], $menu->getSubmenus());
        $this->assertFalse($menu->hasSubmenus());
    }

    /**
     * Test the submenu receives the given title and slug.
     *
     * @return void
     */
    public function testAddSubmenuStoresTheGivenTitleAndSlug(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $submenu = $menu->addSubmenu('All Tasks', 'omega-tasks-all');

        $this->assertInstanceOf(Submenu::class, $submenu);
        $this->assertSame('All Tasks', $submenu->getTitle());
        $this->assertSame('omega-tasks-all', $submenu->getSlug());
        $this->assertSame([$submenu], $menu->getSubmenus());
        $this->assertTrue($menu->hasSubmenus());
    }

    /**
     * Test the parent slug is used when no submenu slug is given.
     *
     * @return void
     */
    public function testAddSubmenuFallsBackToTheParentSlug(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $submenu = $menu->addSubmenu('Tasks');

        $this->assertSame('omega-tasks', $submenu->getSlug());
        $this->assertSame('Tasks', $submenu->getTitle());
    }

    /**
     * Test the submenu keeps a reference to the parent menu instance.
     *
     * @return void
     */
    public function testAddSubmenuStoresTheParentMenuInstance(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $this->assertSame($menu, $menu->addSubmenu('All Tasks')->getParentMenu());
    }

    /**
     * Test the submenus are collected in registration order.
     *
     * @return void
     */
    public function testAddSubmenuCollectsTheSubmenusInOrder(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $menu->addSubmenu('First', 'omega-tasks-first');
        $menu->addSubmenu('Second', 'omega-tasks-second');

        $this->assertSame(
            ['omega-tasks-first', 'omega-tasks-second'],
            array_map(
                static fn(Submenu $submenu): string => $submenu->getSlug(),
                $menu->getSubmenus()
            )
        );
    }

    /**
     * Test the submenu inherits the default capability of the menu item.
     *
     * @return void
     */
    public function testAddSubmenuInheritsTheDefaultCapability(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $this->assertSame('manage_options', $menu->addSubmenu('All Tasks')->getCapability());
    }

    /**
     * Test the submenu slug is expanded with the routing path.
     *
     * @return void
     */
    public function testAddSubmenuExpandsTheSlugWithTheRoutingPath(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $submenu = $menu->addSubmenu('All Tasks', 'omega-tasks-all')->path('admin/tasks');

        $this->assertSame('omega-tasks-all&path=admin/tasks', $submenu->getSlug());
    }

    /**
     * Test the submenus of two menus do not leak between instances.
     *
     * @return void
     */
    public function testSubmenusAreNotSharedBetweenMenus(): void
    {
        $first  = (new Menu())->slug('omega-first')->title('First');
        $second = (new Menu())->slug('omega-second')->title('Second');

        $first->addSubmenu('Only First', 'omega-first-child');

        $this->assertSame([], $second->getSubmenus());
        $this->assertFalse($second->hasSubmenus());
    }
}
