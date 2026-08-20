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
use ReflectionMethod;
use Tests\Routing\Support\WPError;
use Tests\Routing\Support\WPRestRequest;
use Tests\Routing\Support\WPRestResponse;

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
     * A string guard in admin mode is passed through unchanged.
     */
    public function testAdminRouteWithStringGuardPassesThrough(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->guards('manage_options');

        $router->addRoute('GET', '/settings', ['Tests\Routing\Support\StubController', 'handle']);

        $submenuArgs = WordPressRuntime::$submenus[0];
        $this->assertSame('manage_options', $submenuArgs[3]);
    }

    /**
     * Guards in non-admin mode pass through unchanged.
     */
    public function testGuardsInNonAdminModePassThrough(): void
    {
        $router = $this->makeRouter();
        $router->guards('edit_posts');
        $router->rest();

        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertCount(1, $router->getRoutes());
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

    /**
     * A controller with constructor whose FormRequest validation fails outputs error.
     */
    public function testProcessAdminRequestWithConstructorOutputsErrorOnValidationFailure(): void
    {
        $router = $this->makeRouter();
        $router->setPage('my-page');
        $router->addRoute('GET', '/page', ['Tests\Routing\Support\ConstructorFormRequestController', 'handle']);

        $callback = WordPressRuntime::$submenus[0][5];
        ob_start();
        $callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('error', $output);
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

    // ────────────────────────────────────────
    // parseUriParameters
    // ────────────────────────────────────────

    /**
     * A route with URI parameters converts placeholders to regex capture groups.
     */
    public function testParseUriParametersConvertsPlaceholdersToRegex(): void
    {
        $router = $this->makeRouter();
        $router->rest();

        $route = $router->addRoute('GET', '/tasks/{id}', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertSame('/tasks/(?P<id>[^/]+)', $route['uri']);
    }

    /**
     * A route with multiple URI parameters converts all placeholders.
     */
    public function testParseUriParametersHandlesMultiplePlaceholders(): void
    {
        $router = $this->makeRouter();
        $router->rest();

        $route = $router->addRoute('GET', '/tasks/{taskId}/items/{itemId}', ['Tests\Routing\Support\StubController', 'handle']);

        $this->assertSame('/tasks/(?P<taskId>[^/]+)/items/(?P<itemId>[^/]+)', $route['uri']);
    }

    // ────────────────────────────────────────
    // registerRestRoute — callback success path
    // ────────────────────────────────────────

    /**
     * The REST callback wraps a successful array response in a WPRestResponse.
     */
    public function testRestCallbackReturnsWrappedResponseOnSuccess(): void
    {
        $router = $this->makeRouter();
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $callback = WordPressRuntime::$restRoutes[0][2]['callback'];
        $result = $callback(new WPRestRequest());

        $this->assertInstanceOf(WPRestResponse::class, $result);
        $this->assertSame(['ok' => true], $result->get_data());
    }

    /**
     * The REST callback catches exceptions and returns a WP_Error.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testRestCallbackReturnsWpErrorOnException(): void
    {
        $router = $this->createPartialMock(Router::class, ['processRequest']);
        $router->method('processRequest')
            ->willThrowException(new \Exception('Unexpected error'));

        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $callback = WordPressRuntime::$restRoutes[0][2]['callback'];
        $result = $callback(new WPRestRequest());

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertSame('server_error', $result->get_error_code());
    }

    /**
     * The REST callback wraps a ResourceCollection response via toArray().
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testRestCallbackWrapsResourceCollectionViaToArray(): void
    {
        $router = $this->createPartialMock(Router::class, ['processRequest']);
        $router->method('processRequest')
            ->willReturn(new \Omega\Http\Json\ResourceCollection(
                new \Omega\Collection\Collection(['a', 'b'])
            ));

        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $callback = WordPressRuntime::$restRoutes[0][2]['callback'];
        $result = $callback(new WPRestRequest());

        $this->assertInstanceOf(WPRestResponse::class, $result);
        $this->assertSame(['data' => ['a', 'b']], $result->get_data());
    }

    /**
     * The REST callback wraps a JsonResource response via toArray().
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testRestCallbackWrapsJsonResourceViaToArray(): void
    {
        $model = $this->createMock(\Omega\Database\ORM\AbstractModel::class);

        $router = $this->createPartialMock(Router::class, ['processRequest']);
        $router->method('processRequest')
            ->willReturn(new \Tests\Routing\Support\TestJsonResource($model));

        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $callback = WordPressRuntime::$restRoutes[0][2]['callback'];
        $result = $callback(new WPRestRequest());

        $this->assertInstanceOf(WPRestResponse::class, $result);
        $this->assertSame(['id' => 1, 'name' => 'test'], $result->get_data());
    }

    // ────────────────────────────────────────
    // registerRestRoute — permission_callback
    // ────────────────────────────────────────

    /**
     * A callable guard returning false blocks the request.
     */
    public function testPermissionCallbackBlocksWhenCallableGuardReturnsFalse(): void
    {
        $router = $this->makeRouter();
        $router->guards([fn(): bool => false]);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertFalse($permissionCallback());
    }

    /**
     * A callable guard returning true allows the request to proceed.
     */
    public function testPermissionCallbackAllowsWhenCallableGuardReturnsTrue(): void
    {
        $router = $this->makeRouter();
        $router->guards([fn(): bool => true]);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertTrue($permissionCallback());
    }

    /**
     * An array guard where any capability is missing blocks the request.
     */
    public function testPermissionCallbackBlocksWhenArrayGuardFails(): void
    {
        $router = $this->makeRouter();
        $router->guards([['cap_a', 'cap_b']]);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        WordPressRuntime::$capabilities = false;
        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertFalse($permissionCallback());
    }

    /**
     * An array guard where all capabilities pass allows the request.
     */
    public function testPermissionCallbackAllowsWhenArrayGuardPasses(): void
    {
        $router = $this->makeRouter();
        $router->guards([['cap_a', 'cap_b']]);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        WordPressRuntime::$capabilities = true;
        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertTrue($permissionCallback());
    }

    /**
     * A string guard where current_user_can returns true allows the request.
     */
    public function testPermissionCallbackAllowsWhenStringGuardPasses(): void
    {
        $router = $this->makeRouter();
        $router->guards(['edit_posts']);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        WordPressRuntime::$capabilities = true;
        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertTrue($permissionCallback());
    }

    /**
     * A string guard where current_user_can returns false blocks the request.
     */
    public function testPermissionCallbackBlocksWhenStringGuardFails(): void
    {
        $router = $this->makeRouter();
        $router->guards(['edit_posts']);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        WordPressRuntime::$capabilities = false;
        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertFalse($permissionCallback());
    }

    /**
     * An empty guards array allows the request unconditionally.
     */
    public function testPermissionCallbackAllowsWithEmptyGuards(): void
    {
        $router = $this->makeRouter();
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertTrue($permissionCallback());
    }

    /**
     * A mix of guard types where all pass allows the request.
     */
    public function testPermissionCallbackAllowsWhenMixedGuardsAllPass(): void
    {
        $router = $this->makeRouter();
        $router->guards([fn(): bool => true, 'edit_posts', ['cap_a']]);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        WordPressRuntime::$capabilities = true;
        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertTrue($permissionCallback());
    }

    /**
     * A mix of guard types where the first fails blocks the request.
     */
    public function testPermissionCallbackBlocksWhenFirstMixedGuardFails(): void
    {
        $router = $this->makeRouter();
        $router->guards([fn(): bool => false, 'edit_posts', ['cap_a']]);
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\StubController', 'handle']);

        WordPressRuntime::$capabilities = true;
        $permissionCallback = WordPressRuntime::$restRoutes[0][2]['permission_callback'];
        $this->assertFalse($permissionCallback());
    }

    // ────────────────────────────────────────
    // processRestRequest
    // ────────────────────────────────────────

    /**
     * Controller with constructor is instantiated via newInstanceArgs (ternary true).
     */
    public function testProcessRestRequestInstantiatesViaConstructorWhenPresent(): void
    {
        $router = $this->makeRouter();
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\ConstructorController', 'handle']);

        $callback = WordPressRuntime::$restRoutes[0][2]['callback'];
        $result = $callback(new WPRestRequest());

        $this->assertInstanceOf(WPRestResponse::class, $result);
        $this->assertSame(['id' => 0], $result->get_data());
    }

    /**
     * Validation failure in FormRequest returns WP_Error (no constructor, if true).
     */
    public function testProcessRestRequestReturnsWpErrorWhenFormRequestValidationFails(): void
    {
        $router = $this->makeRouter();
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\FormRequestController', 'handle']);

        $action = ['Tests\Routing\Support\FormRequestController', 'handle'];
        $method = new ReflectionMethod($router, 'processRestRequest');
        $result = $method->invoke($router, $action, new WPRestRequest());

        $this->assertInstanceOf(WPError::class, $result);
    }

    /**
     * Controller with constructor + method FormRequest failure returns WP_Error (ternary true, if true).
     */
    public function testProcessRestRequestWithConstructorReturnsWpErrorOnMethodDepsFailure(): void
    {
        $router = $this->makeRouter();
        $router->addRoute('GET', '/items', ['Tests\Routing\Support\ConstructorFormRequestController', 'handle']);

        $action = ['Tests\Routing\Support\ConstructorFormRequestController', 'handle'];
        $method = new ReflectionMethod($router, 'processRestRequest');
        $result = $method->invoke($router, $action, new WPRestRequest());

        $this->assertInstanceOf(WPError::class, $result);
    }

    /**
     * applyPrefix filters out prefixStack items whose depth exceeds groupDepth.
     */
    public function testApplyPrefixFiltersOutHighDepthPrefixes(): void
    {
        $router = $this->makeRouter();

        $prop = new \ReflectionProperty($router, 'prefixStack');
        $prop->setValue($router, [5 => ['prefix' => 'deep', 'depth' => 5]]);

        $router->addRoute('GET', '/tasks', ['Tests\Routing\Support\StubController', 'handle']);

        [$namespace] = WordPressRuntime::$restRoutes[0];
        $this->assertSame('', $namespace);
    }
}
