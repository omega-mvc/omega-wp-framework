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

use Omega\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the Router REST registration behavior.
 *
 * Locks the expected namespace/guard resolution semantics so that
 * regressions in prefix or guard propagation fail loudly.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Router::class)]
final class RouterTest extends RoutingTestCase
{
    /**
     * A prefix declared at the top level (no group) must reach the namespace.
     */
    public function testPrefixAppliesToTopLevelRouteWithoutGroup(): void
    {
        $router = $this->makeRouter();

        $router->prefix('v1');
        $router->addRoute('GET', '/tasks', ['App\Http\Controllers\TaskController', 'index']);

        $this->assertCount(1, WordPressRuntime::$restRoutes);
        [$namespace] = WordPressRuntime::$restRoutes[0];
        $this->assertSame('v1', $namespace);
    }

    /**
     * Guards declared at the top level (no group) must gate the route.
     */
    public function testGuardsApplyToTopLevelRouteWithoutGroup(): void
    {
        $router = $this->makeRouter();

        $router->guards(['edit_posts']);
        $router->addRoute('GET', '/tasks', ['App\Http\Controllers\TaskController', 'index']);

        WordPressRuntime::$capabilities = false;
        $this->assertFalse(WordPressRuntime::$restRoutes[0][2]['permission_callback']());
    }

    /**
     * A prefix and guards set inside a group must be applied to the route.
     */
    public function testPrefixAndGuardsSetInsideGroupAreApplied(): void
    {
        $router = $this->makeRouter();

        $router->group(function (Router $router): void {
            $router->prefix('sub')->guards(['edit_posts']);
            $router->addRoute('GET', '/items', ['App\Http\Controllers\TaskController', 'index']);
        });

        [$namespace] = WordPressRuntime::$restRoutes[0];
        $this->assertSame('sub', $namespace);

        WordPressRuntime::$capabilities = false;
        $this->assertFalse(WordPressRuntime::$restRoutes[0][2]['permission_callback']());
    }

    /**
     * Top-level prefix + guards combined with a group keep working.
     */
    public function testTopLevelPrefixAndGuardsWithGroupAreApplied(): void
    {
        $router = $this->makeRouter();

        $router->prefix('omega-wp/v1')->guards(['edit_posts']);
        $router->group(function (Router $router): void {
            $router->addRoute('GET', '/tasks', ['App\Http\Controllers\TaskController', 'index']);
        });

        [$namespace, $route] = WordPressRuntime::$restRoutes[0];
        $this->assertSame('omega-wp/v1', $namespace);
        $this->assertSame('/tasks', $route);

        WordPressRuntime::$capabilities = false;
        $this->assertFalse(WordPressRuntime::$restRoutes[0][2]['permission_callback']());
    }

    /**
     * Mixed guard shapes (array and scalar) must not raise a TypeError.
     */
    public function testMixedGuardShapesAreFlattenedWithoutTypeError(): void
    {
        $router = $this->makeRouter();

        $router->guards(['cap_a']);
        $router->group(function (Router $router): void {
            $router->guards('cap_b');
            $router->group(function (Router $router): void {
                $router->addRoute('GET', '/nested', ['App\Http\Controllers\TaskController', 'index']);
            });
        });

        WordPressRuntime::$capabilities = false;
        $this->assertFalse(WordPressRuntime::$restRoutes[0][2]['permission_callback']());
    }

    /**
     * An array of HTTP methods must be accepted on a route.
     */
    public function testArrayHttpMethodsAreSupported(): void
    {
        $router = $this->makeRouter();

        $router->addRoute(['GET', 'POST'], '/tasks', ['App\Http\Controllers\TaskController', 'index']);

        $this->assertSame(['GET', 'POST'], WordPressRuntime::$restRoutes[0][2]['methods']);
    }
}
