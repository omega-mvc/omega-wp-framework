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

use Omega\Application\Application;
use Omega\Routing\Router;
use Omega\Routing\RouterBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the RouterBuilder route registration and admin page behavior.
 *
 * Covers prefix propagation, HTTP method registration (get/post/put/delete),
 * admin route guards, and submenu parent resolution.
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
    // ── prefix ──────────────────────────────────────────────────

    /**
     * prefix() must return a Router instance.
     */
    public function testPrefixReturnsRouterInstance(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $result = $builder->prefix('v1');

        $this->assertInstanceOf(Router::class, $result);
    }

    /**
     * A prefix set via the builder must apply to the next registered route.
     */
    public function testPrefixAppliesToNextRoute(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->prefix('omega-wp/v1')->group(function () use ($builder): void {
            $builder->get('/tasks', ['App\Http\Controllers\Tasks\IndexController', 'handle']);
        });

        [$namespace] = WordPressRuntime::$restRoutes[0];
        $this->assertSame('omega-wp/v1', $namespace);
    }

    // ── get / post / put / delete ───────────────────────────────

    /**
     * get() must register a route with the GET method.
     */
    public function testGetRegistersGetRoute(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->get('/items', ['App\Http\Controllers\Tasks\IndexController', 'handle']);

        $this->assertCount(1, WordPressRuntime::$restRoutes);
        $this->assertSame('GET', WordPressRuntime::$restRoutes[0][2]['methods']);
        $this->assertSame('/items', WordPressRuntime::$restRoutes[0][1]);
    }

    /**
     * post() must register a route with the POST method.
     */
    public function testPostRegistersPostRoute(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->post('/items', ['App\Http\Controllers\Tasks\StoreController', 'handle']);

        $this->assertCount(1, WordPressRuntime::$restRoutes);
        $this->assertSame('POST', WordPressRuntime::$restRoutes[0][2]['methods']);
        $this->assertSame('/items', WordPressRuntime::$restRoutes[0][1]);
    }

    /**
     * put() must register a route with the PUT method.
     */
    public function testPutRegistersPutRoute(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->put('/items/1', ['App\Http\Controllers\Tasks\UpdateController', 'handle']);

        $this->assertCount(1, WordPressRuntime::$restRoutes);
        $this->assertSame('PUT', WordPressRuntime::$restRoutes[0][2]['methods']);
        $this->assertSame('/items/1', WordPressRuntime::$restRoutes[0][1]);
    }

    /**
     * patch() must register a route with the PATCH method.
     */
    public function testPatchRegistersPatchRoute(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->patch('/items/1', ['App\Http\Controllers\Tasks\UpdateController', 'handle']);

        $this->assertCount(1, WordPressRuntime::$restRoutes);
        $this->assertSame('PATCH', WordPressRuntime::$restRoutes[0][2]['methods']);
        $this->assertSame('/items/1', WordPressRuntime::$restRoutes[0][1]);
    }

    /**
     * delete() must register a route with the DELETE method.
     */
    public function testDeleteRegistersDeleteRoute(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->delete('/items/1', ['App\Http\Controllers\Tasks\DestroyController', 'handle']);

        $this->assertCount(1, WordPressRuntime::$restRoutes);
        $this->assertSame('DELETE', WordPressRuntime::$restRoutes[0][2]['methods']);
        $this->assertSame('/items/1', WordPressRuntime::$restRoutes[0][1]);
    }

    /**
     * Multiple HTTP methods on different URIs must be independent.
     */
    public function testMultipleRoutesAreIndependent(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->get('/items', ['App\Http\Controllers\Tasks\IndexController', 'handle']);
        $builder->post('/items', ['App\Http\Controllers\Tasks\StoreController', 'handle']);

        $this->assertCount(2, WordPressRuntime::$restRoutes);
        $this->assertSame('GET', WordPressRuntime::$restRoutes[0][2]['methods']);
        $this->assertSame('POST', WordPressRuntime::$restRoutes[1][2]['methods']);
    }

    // ── getInstance path coverage ───────────────────────────────

    /**
     * getInstance() creates a new Router when groupDepth > 0 but instances is empty.
     *
     * Covers the path where the first operand of the && condition is true
     * but the second is false (T,F).
     */
    public function testGetInstanceCreatesNewWhenGroupDepthPositiveWithNoInstances(): void
    {
        $builder = new RouterBuilder($this->createStub(Application::class));

        $builder->increaseGroupDepth();

        $builder->get('/items', ['App\Http\Controllers\Tasks\IndexController', 'handle']);

        $this->assertCount(1, WordPressRuntime::$restRoutes);
        $this->assertSame('GET', WordPressRuntime::$restRoutes[0][2]['methods']);
    }

    // ── Admin page (existing) ───────────────────────────────────

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
