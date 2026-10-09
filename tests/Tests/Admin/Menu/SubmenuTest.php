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
use TypeError;

/**
 * Test the Submenu class.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Submenu::class)]
final class SubmenuTest extends TestCase
{
    /**
     * Test a submenu has no parent menu by default.
     *
     * @return void
     */
    public function testItHasNoParentMenuByDefault(): void
    {
        $this->assertNull((new Submenu())->getParentMenu());
    }

    /**
     * Test the parent menu given to the constructor is exposed.
     *
     * @return void
     */
    public function testItExposesTheParentMenuGivenToTheConstructor(): void
    {
        $menu = (new Menu())->slug('omega-tasks')->title('Tasks');

        $this->assertSame($menu, (new Submenu($menu))->getParentMenu());
    }

    /**
     * Test the parent menu can be replaced after construction.
     *
     * @return void
     */
    public function testItReplacesTheParentMenuFluently(): void
    {
        $first  = (new Menu())->slug('omega-first')->title('First');
        $second = (new Menu())->slug('omega-second')->title('Second');

        $submenu = new Submenu($first);
        $returned = $submenu->setParentMenu($second);

        $this->assertSame($submenu, $returned);
        $this->assertSame($second, $submenu->getParentMenu());
    }

    /**
     * Test the parent menu also accepts a plain identifier.
     *
     * @return void
     */
    public function testItAcceptsAPlainParentIdentifier(): void
    {
        $submenu = (new Submenu())->setParentMenu('omega-tasks');

        $this->assertSame('omega-tasks', $submenu->getParentMenu());
    }

    /**
     * Test the callback is stored and returned.
     *
     * @return void
     */
    public function testItStoresAndReturnsTheCallback(): void
    {
        $callback = static function (): string {
            return 'rendered';
        };

        $submenu = new Submenu();
        $returned = $submenu->setCallback($callback);

        $this->assertSame($submenu, $returned);
        $this->assertSame($callback, $submenu->getCallback());
        $this->assertSame('rendered', ($submenu->getCallback())());
    }

    /**
     * Test reading the callback before assignment is a type error.
     *
     * @return void
     */
    public function testReadingAnUnsetCallbackIsATypeError(): void
    {
        $this->expectException(TypeError::class);

        $callback = (new Submenu())->getCallback();
    }

    /**
     * Test the slug is returned untouched when no routing path is set.
     *
     * @return void
     */
    public function testItReturnsTheSlugWhenNoRoutingPathIsSet(): void
    {
        $submenu = (new Submenu())->slug('omega-tasks-all');

        $this->assertSame('omega-tasks-all', $submenu->getSlug());
    }

    /**
     * Test the slug is expanded with the routing path as query argument.
     *
     * @return void
     */
    public function testItExpandsTheSlugWithTheRoutingPath(): void
    {
        $submenu = (new Submenu())->slug('omega-tasks-all')->path('admin/tasks');

        $this->assertSame('omega-tasks-all&path=admin/tasks', $submenu->getSlug());
    }

    /**
     * Test an empty routing path leaves the slug untouched.
     *
     * @return void
     */
    public function testItKeepsTheSlugWhenTheRoutingPathIsEmpty(): void
    {
        $submenu = (new Submenu())->slug('omega-tasks-all')->path('');

        $this->assertSame('omega-tasks-all', $submenu->getSlug());
    }

    /**
     * Test the array representation ignores the parent menu and the callback.
     *
     * @return void
     */
    public function testToArrayIgnoresTheParentMenuAndTheCallback(): void
    {
        $submenu = (new Submenu((new Menu())->slug('omega-tasks')))
            ->slug('omega-tasks-all')
            ->title('All Tasks')
            ->setCallback(static fn(): null => null);

        $this->assertSame(
            [
                'slug'       => 'omega-tasks-all',
                'title'      => 'All Tasks',
                'capability' => 'manage_options',
                'icon'       => '',
                'path'       => '',
                'view'       => '',
                'position'   => null,
                'scripts'    => [],
            ],
            $submenu->toArray()
        );
    }
}
