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

use Omega\Routing\RouterBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the RouterBuilder admin page behavior.
 *
 * Guards declared on a page must survive until the submenu is registered
 * and the submenu must always reference a real (or missing) parent menu.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(RouterBuilder::class)]
final class RouterBuilderTest extends RoutingTestCase
{
    /**
     * Guards set on the page child must be the submenu capability.
     */
    public function testAdminRouteKeepsGuardsConfiguredOnPage(): void
    {
        $builder = $this->makeBuilder();

        $builder->page('my-page-id')->guards(['edit_posts'])->group(function () use ($builder): void {
            $builder->get('/path-example', ['App\Http\Controllers\TaskController', 'create']);
        });

        $this->assertCount(1, WordPressRuntime::$submenus);
        $this->assertSame('edit_posts', WordPressRuntime::$submenus[0][3]);
    }

    /**
     * The submenu parent must be a registered top-level menu or null.
     */
    public function testAdminSubmenuParentIsRegisteredMenu(): void
    {
        $builder = $this->makeBuilder();

        $builder->page('my-page-id')->group(function () use ($builder): void {
            $builder->get('/path-example', ['App\Http\Controllers\TaskController', 'create']);
        });

        $this->assertCount(1, WordPressRuntime::$submenus);
        $parent = WordPressRuntime::$submenus[0][0];
        $menuSlugs = array_map(
            static fn(array $menu): string => (string) $menu[2],
            WordPressRuntime::$menus,
        );

        $this->assertTrue(
            in_array($parent, $menuSlugs, true) || $parent === null,
            "Submenu parent '{$parent}' must be a registered top-level menu or null.",
        );
    }
}
