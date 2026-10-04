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

use Omega\Admin\Menu\AbstractMenuBuilder;
use Omega\Admin\Menu\Menu;
use Omega\Admin\Menu\Submenu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Admin\AdminTestCase;
use Tests\Admin\Fixtures\EmptyAdminMenu;
use Tests\Admin\Fixtures\HookedAdminMenu;
use Tests\Admin\Fixtures\SampleAdminMenu;
use Tests\Routing\WordPressRuntime;

use function array_filter;
use function array_key_last;
use function array_values;
use function is_callable;

/**
 * Test the AbstractMenuBuilder registration flow.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractMenuBuilder::class)]
final class AbstractMenuBuilderTest extends AdminTestCase
{
    /**
     * Return a fresh builder bound to the application under test.
     *
     * @return AbstractMenuBuilder The builder instance
     */
    private function builder(): AbstractMenuBuilder
    {
        return new EmptyAdminMenu($this->app);
    }

    /**
     * Return the positional arguments of the last hook registered for a name.
     *
     * The plugin fixture boot already registers an admin_menu hook, so the
     * last registration is the one performed by the builder under test.
     *
     * @param string $hookName Hook name to look up
     * @return array<int|string, mixed> The recorded hook arguments
     */
    private function recordedAction(string $hookName): array
    {
        $recorded = array_values(array_filter(
            WordPressRuntime::$actions,
            static fn(array $action): bool => $action[0] === $hookName
        ));

        if ($recorded === []) {
            $this->fail(sprintf('No "%s" hook has been registered.', $hookName));
        }

        return $recorded[array_key_last($recorded)];
    }

    /**
     * Run the callback registered last for the given hook name.
     *
     * @param string $hookName Hook name to look up
     * @return void
     */
    private function runAction(string $hookName): void
    {
        $callback = $this->recordedAction($hookName)[1] ?? null;

        if (!is_callable($callback)) {
            $this->fail(sprintf('No callable has been registered for the "%s" hook.', $hookName));
        }

        $callback();
    }

    /**
     * Test the add() helper returns a menu carrying the given slug and title.
     *
     * @return void
     */
    public function testAddReturnsAMenuWithTheGivenSlugAndTitle(): void
    {
        $menu = $this->builder()->add('omega-tasks', 'Tasks');

        $this->assertInstanceOf(Menu::class, $menu);
        $this->assertSame('omega-tasks', $menu->getSlug());
        $this->assertSame('Tasks', $menu->getTitle());
    }

    /**
     * Test add() registers every menu, not only the last one.
     *
     * @return void
     */
    public function testAddRegistersEveryMenu(): void
    {
        $builder = $this->builder();
        $builder->add('omega-first', 'First');
        $builder->add('omega-second', 'Second');

        $builder->create();

        $this->assertSame('omega-first', WordPressRuntime::$menus[0][3]);
        $this->assertSame('omega-second', WordPressRuntime::$menus[1][3]);
    }

    /**
     * Test create() passes the menu metadata to WordPress.
     *
     * @return void
     */
    public function testCreatePassesTheMenuMetadataToWordPress(): void
    {
        $builder = new SampleAdminMenu($this->app);
        $builder->register();

        $builder->create();

        $this->assertCount(1, WordPressRuntime::$menus);
        $this->assertSame('Tasks', WordPressRuntime::$menus[0][0]);
        $this->assertSame('Tasks', WordPressRuntime::$menus[0][1]);
        $this->assertSame('edit_posts', WordPressRuntime::$menus[0][2]);
        $this->assertSame('omega-tasks', WordPressRuntime::$menus[0][3]);
        $this->assertSame('dashicons-lightbulb', WordPressRuntime::$menus[0][5]);
        $this->assertSame(58, WordPressRuntime::$menus[0][6]);
    }

    /**
     * Test create() passes a rendering callback to WordPress.
     *
     * @return void
     */
    public function testCreatePassesARenderingCallbackToWordPress(): void
    {
        $builder = new SampleAdminMenu($this->app);
        $builder->register();

        $builder->create();

        $this->assertIsCallable(WordPressRuntime::$menus[0][4]);
    }

    /**
     * Test create() registers the submenus declared on each menu.
     *
     * @return void
     */
    public function testCreateRegistersTheSubmenusOfEachMenu(): void
    {
        $builder = new SampleAdminMenu($this->app);
        $builder->register();

        $builder->create();

        $this->assertCount(2, WordPressRuntime::$submenus);
        $this->assertSame('omega-tasks', WordPressRuntime::$submenus[0][0]);
        $this->assertSame('All Tasks', WordPressRuntime::$submenus[0][1]);
        $this->assertSame('All Tasks', WordPressRuntime::$submenus[0][2]);
        $this->assertSame('omega-tasks-all', WordPressRuntime::$submenus[0][4]);
        $this->assertSame('omega-tasks', WordPressRuntime::$submenus[1][0]);
        $this->assertSame('Archived Tasks', WordPressRuntime::$submenus[1][1]);
    }

    /**
     * Test create() registers the submenus with the default capability.
     *
     * A submenu declared on a menu keeps its own capability, so the value
     * configured on the parent menu is not inherited.
     *
     * @return void
     */
    public function testCreateRegistersTheSubmenusWithTheDefaultCapability(): void
    {
        $builder = new SampleAdminMenu($this->app);
        $builder->register();

        $builder->create();

        $this->assertSame('edit_posts', WordPressRuntime::$menus[0][2]);
        $this->assertSame('manage_options', WordPressRuntime::$submenus[0][3]);
    }

    /**
     * Test create() removes the submenu WordPress duplicates for a menu.
     *
     * @return void
     */
    public function testCreateRemovesTheDuplicatedSubmenuOfEachMenu(): void
    {
        $builder = new SampleAdminMenu($this->app);
        $builder->register();

        $builder->create();

        $this->assertSame([['omega-tasks', 'omega-tasks']], WordPressRuntime::$removedSubmenus);
    }

    /**
     * Test create() without a declared menu registers nothing.
     *
     * @return void
     */
    public function testCreateWithoutMenusRegistersNothing(): void
    {
        $builder = new EmptyAdminMenu($this->app);
        $builder->register();

        $builder->create();

        $this->assertSame([], WordPressRuntime::$menus);
        $this->assertSame([], WordPressRuntime::$submenus);
        $this->assertSame([], WordPressRuntime::$removedSubmenus);
    }

    /**
     * Test addSubmenu() defers the WordPress registration to the admin_menu hook.
     *
     * @return void
     */
    public function testAddSubmenuDefersTheRegistrationToTheAdminMenuHook(): void
    {
        $builder = new HookedAdminMenu($this->app);
        $builder->register();

        $this->assertSame([], WordPressRuntime::$submenus);

        $this->runAction('admin_menu');

        $this->assertCount(1, WordPressRuntime::$submenus);
    }

    /**
     * Test addSubmenu() registers the submenu under the requested priority.
     *
     * @return void
     */
    public function testAddSubmenuRegistersTheHookWithTheRequestedPriority(): void
    {
        $builder = new HookedAdminMenu($this->app);
        $builder->register();

        $this->assertSame(25, $this->recordedAction('admin_menu')[2]);
    }

    /**
     * Test the default submenu priority is used when none is given.
     *
     * @return void
     */
    public function testAddSubmenuDefaultsToPriorityNinetyNine(): void
    {
        $builder = $this->builder();
        $builder->add('omega-parent', 'Parent')->addSubmenu('Child')->setCallback(static fn(): null => null);

        $this->assertSame(99, $this->recordedAction('admin_menu')[2]);
    }

    /**
     * Test addSubmenu() forwards the submenu metadata to add_submenu_page().
     *
     * @return void
     */
    public function testAddSubmenuForwardsTheSubmenuMetadataToWordPress(): void
    {
        $builder = new HookedAdminMenu($this->app);
        $builder->register();

        $this->runAction('admin_menu');

        $this->assertSame('omega-hooks', WordPressRuntime::$submenus[0][0]);
        $this->assertSame('Child', WordPressRuntime::$submenus[0][1]);
        $this->assertSame('Child', WordPressRuntime::$submenus[0][2]);
        $this->assertSame('read', WordPressRuntime::$submenus[0][3]);
        $this->assertSame('omega-hooks-child', WordPressRuntime::$submenus[0][4]);
        $this->assertIsCallable(WordPressRuntime::$submenus[0][5]);
    }

    /**
     * Test addSubmenu() returns a submenu carrying the given parent and slug.
     *
     * @return void
     */
    public function testAddSubmenuReturnsTheConfiguredSubmenu(): void
    {
        $submenu = $this->builder()->addSubmenu('omega-parent', 'omega-child', 'Child');

        $this->assertInstanceOf(Submenu::class, $submenu);
        $this->assertSame('omega-parent', $submenu->getParentMenu());
        $this->assertSame('omega-child', $submenu->getSlug());
        $this->assertSame('Child', $submenu->getTitle());
    }

    /**
     * Test addSubmenu() does not attach the submenu to the top-level menu.
     *
     * @return void
     */
    public function testAddSubmenuDoesNotAttachTheSubmenuToTheMenu(): void
    {
        $builder = $this->builder();
        $menu    = $builder->add('omega-parent', 'Parent');
        $builder->addSubmenu('omega-parent', 'omega-child', 'Child');

        $this->assertSame([], $menu->getSubmenus());
        $this->assertFalse($menu->hasSubmenus());
    }

    /**
     * Test every declared menu keeps its own submenus and position.
     *
     * @return void
     */
    #[DataProvider('menuMetadataProvider')]
    public function testCreateUsesTheMetadataOfEachMenu(string $slug, string $title, int $position): void
    {
        $builder = $this->builder();
        $builder->add($slug, $title)->position($position);
        $builder->add($slug . '-other', $title . ' Other')->position($position + 1);

        $builder->create();

        $this->assertSame($title, WordPressRuntime::$menus[0][0]);
        $this->assertSame($slug, WordPressRuntime::$menus[0][3]);
        $this->assertSame($position, WordPressRuntime::$menus[0][6]);
        $this->assertSame($title . ' Other', WordPressRuntime::$menus[1][0]);
        $this->assertSame($slug . '-other', WordPressRuntime::$menus[1][3]);
        $this->assertSame($position + 1, WordPressRuntime::$menus[1][6]);
    }

    /**
     * Menus declared with distinct metadata.
     *
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function menuMetadataProvider(): array
    {
        return [
            'first menu'  => ['omega-tasks', 'Tasks', 26],
            'second menu' => ['omega-reports', 'Reports', 80],
        ];
    }
}
