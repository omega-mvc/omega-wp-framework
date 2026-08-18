<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * Coverage tests for the 6 Router methods:
 * registerAdminRoute, processAdminRequest, setPage, rest, admin, page.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Routing;

use Omega\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\Support\WPError;

/**
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Router::class)]
final class RouterMethodsCoverageTest extends RoutingTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_GET['path']);
    }

    // ────────────────────────────────────────
    // registerAdminRoute
    // ────────────────────────────────────────

    /**
     * When no guards are set the default 'manage_options' capability is used.
     */
    public function testAdminRouteUsesDefaultGuardWhenNoGuardsSet(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');

        $router->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        $submenuArgs = WordPressRuntime::$submenus[0];
        $this->assertSame('manage_options', $submenuArgs[3]);
    }

    /**
     * The first guard from the array is used as admin capability.
     */
    public function testAdminRouteUsesFirstGuardAsCapability(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->guards(['edit_others_posts']);

        $router->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        $submenuArgs = WordPressRuntime::$submenus[0];
        $this->assertSame('edit_others_posts', $submenuArgs[3]);
    }

    /**
     * When $_GET['path'] is not set the admin callback processes the request.
     */
    public function testAdminCallbackProcessesWhenPathNotSet(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        unset($_GET['path']);
        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('ok', $output);
    }

    /**
     * When $_GET['path'] matches the route path the request is processed.
     */
    public function testAdminCallbackProcessesWhenPathMatches(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        $_GET['path'] = '/settings';
        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('ok', $output);
    }

    /**
     * When path is a wildcard '*') any path triggers processing.
     */
    public function testAdminCallbackProcessesWhenPathIsWildcard(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '*', ['Tests\Routing\Support\StubController', 'handle']);

        $_GET['path'] = '/something/else';
        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('ok', $output);
    }

    /**
     * When $_GET['path'] is set but does not match, a WP_Error is returned.
     */
    public function testAdminCallbackReturnsErrorWhenPathDoesNotMatch(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        $_GET['path'] = '/other';
        $callback = WordPressRuntime::$submenus[0][5];
        $result = $callback();

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    // ────────────────────────────────────────
    // processAdminRequest
    // ────────────────────────────────────────

    /**
     * A controller returning a string echoes the string directly.
     */
    public function testProcessAdminRequestOutputsStringResult(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\StubController', 'withString']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('<p>html output</p>', $output);
    }

    /**
     * A controller returning an array outputs a formatted pre block.
     */
    public function testProcessAdminRequestOutputsArrayResult(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\StubController', 'handle']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('ok', $output);
        $this->assertStringContainsString('<pre>', $output);
    }

    /**
     * A controller returning void produces no output.
     */
    public function testProcessAdminRequestHandlesVoidReturn(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\StubController', 'returnsNull']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    /**
     * Dependency resolution failure renders an error div.
     */
    public function testProcessAdminRequestOutputsErrorOnResolutionFailure(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\FormRequestController', 'handle']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('error', $output);
    }

    /**
     * A controller with constructor returning array outputs formatted pre block.
     */
    public function testProcessAdminRequestWithConstructorOutputsArrayResult(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\ConstructorStringController', 'handle']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('<pre>', $output);
        $this->assertStringContainsString('id', $output);
    }

    /**
     * A controller with constructor returning string echoes directly.
     */
    public function testProcessAdminRequestWithConstructorOutputsStringResult(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\ConstructorStringController', 'withString']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('<p>html output</p>', $output);
    }

    /**
     * A controller with constructor returning void produces no output.
     */
    public function testProcessAdminRequestWithConstructorHandlesVoidReturn(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\ConstructorStringController', 'returnsNull']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    // ────────────────────────────────────────
    // setPage
    // ────────────────────────────────────────

    /**
     * setPage sets the page and switches mode to admin.
     */
    public function testSetPageSetsPageAndSwitchesToAdmin(): void
    {
        $router = $this->makeRouter();
        $router->rest();
        $router->setPage('my-page');

        $router->addRoute('GET', '/test', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertNotEmpty(WordPressRuntime::$submenus);
        $this->assertEmpty(WordPressRuntime::$restRoutes);
    }

    /**
     * setPage propagates the page to the parent router.
     */
    public function testSetPagePropagatesToParentRouter(): void
    {
        $parent = $this->makeRouter();
        $child = $parent->page('child-page');

        $child->addRoute('GET', '/test', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertNotEmpty(WordPressRuntime::$submenus);
        $this->assertSame('child-page', WordPressRuntime::$submenus[0][2]);
    }

    // ────────────────────────────────────────
    // rest
    // ────────────────────────────────────────

    /**
     * rest() switches mode to REST after a previous admin context.
     */
    public function testRestSwitchesToRestAfterAdminContext(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->rest();

        $router->addRoute('GET', '/api/test', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertNotEmpty(WordPressRuntime::$restRoutes);
        $this->assertEmpty(WordPressRuntime::$submenus);
    }

    /**
     * rest() returns the router instance for chaining.
     */
    public function testRestReturnsRouterInstance(): void
    {
        $router = $this->makeRouter();
        $result = $router->rest();

        $this->assertSame($router, $result);
    }

    // ────────────────────────────────────────
    // admin
    // ────────────────────────────────────────

    /**
     * admin() switches mode to admin and routes register as submenu pages.
     */
    public function testAdminSwitchesModeToAdmin(): void
    {
        $router = $this->makeRouter();
        $router->admin();
        $router->setPage('my-page');

        $router->addRoute('GET', '/test', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertNotEmpty(WordPressRuntime::$submenus);
        $this->assertEmpty(WordPressRuntime::$restRoutes);
    }

    /**
     * admin() returns the router instance for chaining.
     */
    public function testAdminReturnsRouterInstance(): void
    {
        $router = $this->makeRouter();
        $result = $router->admin();

        $this->assertSame($router, $result);
    }

    // ────────────────────────────────────────
    // page
    // ────────────────────────────────────────

    /**
     * page() creates a child router that registers admin routes.
     */
    public function testPageCreatesChildRouterWithAdminRoutes(): void
    {
        $router = $this->makeRouter();
        $child = $router->page('child-page');

        $child->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertNotEmpty(WordPressRuntime::$submenus);
        $this->assertEmpty(WordPressRuntime::$restRoutes);
        $this->assertSame('child-page', WordPressRuntime::$submenus[0][2]);
    }

    /**
     * page() returns a new Router instance that is not the same object.
     */
    public function testPageReturnsNewInstance(): void
    {
        $router = $this->makeRouter();
        $child = $router->page('child-page');

        $this->assertNotSame($router, $child);
        $this->assertInstanceOf(Router::class, $child);
    }

    /**
     * page() with options does not alter routing behavior.
     */
    public function testPageWithOptionsRegistersCorrectly(): void
    {
        $router = $this->makeRouter();
        $child = $router->page('child-page', ['title' => 'Settings']);

        $child->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertCount(1, WordPressRuntime::$submenus);
    }

    /**
     * Nested groups on a child page router clean up prefix/guard stacks.
     */
    public function testNestedGroupsOnChildPageRouterCleanUpCorrectly(): void
    {
        $router = $this->makeRouter();
        $child = $router->page('child-page');

        $child->prefix('api');
        $child->guards(['edit_posts']);
        $child->group(function (Router $r): void {
            $r->prefix('sub');
            $r->guards(['edit_others_posts']);
            $r->addRoute('GET', '/nested', ['Tests\Routing\Support\StubController', 'handle']);
        });

        $child->addRoute('GET', '/top-level', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertCount(2, WordPressRuntime::$submenus);
    }

    // ────────────────────────────────────────
    // getRoutes
    // ────────────────────────────────────────

    /**
     * A fresh router returns an empty routes array.
     */
    public function testGetRoutesReturnsEmptyArrayByDefault(): void
    {
        $router = $this->makeRouter();

        $this->assertSame([], $router->getRoutes());
    }

    /**
     * After registering a route, getRoutes returns it in the route list.
     */
    public function testGetRoutesReturnsRegisteredRoutes(): void
    {
        $router = $this->makeRouter();
        $router->rest();

        $route = $router->addRoute('GET', '/tasks', ['Tests\Routing\Support\StubController', 'handle']);

        $routes = $router->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame($route, $routes[0]);
        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('/tasks', $routes[0]['uri']);
        $this->assertSame(['Tests\Routing\Support\StubController', 'handle'], $routes[0]['action']);
        $this->assertSame([], $routes[0]['guards']);
    }
}
