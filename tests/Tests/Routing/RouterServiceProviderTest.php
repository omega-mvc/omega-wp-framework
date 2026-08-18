<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Routing;

use Omega\Application\Application;
use Omega\Routing\RouteLoader;
use Omega\Routing\RouterBuilder;
use Omega\Routing\RouterServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;



/**
 * Tests the RouterServiceProvider registration and boot behavior.
 *
 * Verifies that the provider binds the router and route loader as singletons
 * and that the boot phase hooks the correct WordPress actions.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(RouterServiceProvider::class)]
final class RouterServiceProviderTest extends RoutingTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/omega-routing-test-' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    // ── register() ──────────────────────────────────────────────

    /**
     * The router key must resolve to a RouterBuilder singleton.
     */
    public function testRegisterBindsRouterBuilderSingleton(): void
    {
        $app = new Application('test-app', $this->tempDir);
        $provider = new RouterServiceProvider($app);

        $provider->register();

        $router = $app->resolve('router');
        $this->assertInstanceOf(RouterBuilder::class, $router);
        $this->assertSame($router, $app->resolve('router'));
    }

    /**
     * The RouteLoader class key must resolve to a RouteLoader singleton.
     */
    public function testRegisterBindsRouteLoaderSingleton(): void
    {
        $app = new Application('test-app', $this->tempDir);
        $provider = new RouterServiceProvider($app);

        $provider->register();

        $loader = $app->resolve(RouteLoader::class);
        $this->assertInstanceOf(RouteLoader::class, $loader);
        $this->assertSame($loader, $app->resolve(RouteLoader::class));
    }

    // ── boot() ──────────────────────────────────────────────────

    /**
     * The boot phase must register the rest_api_init hook.
     */
    public function testBootRegistersRestApiInitHook(): void
    {
        $provider = new RouterServiceProvider($this->createStub(Application::class));

        $provider->boot();

        $this->assertNotNull(
            $this->findAction('rest_api_init'),
            'rest_api_init hook must be registered.',
        );
    }

    /**
     * The boot phase must register the admin_menu hook with priority 99.
     */
    public function testBootRegistersAdminMenuHookWithPriority99(): void
    {
        $provider = new RouterServiceProvider($this->createStub(Application::class));

        $provider->boot();

        $adminHook = $this->findAction('admin_menu');

        $this->assertNotNull($adminHook, 'admin_menu hook must be registered.');
        $this->assertSame(99, $adminHook[2]);
    }

    /**
     * The rest_api_init closure must call loadRestRoutes() on the RouteLoader.
     */
    public function testRestApiInitHookLoadsRestRoutes(): void
    {
        $mockLoader = $this->createMock(RouteLoader::class);
        $mockLoader->expects($this->once())->method('loadRestRoutes');

        $app = $this->createStub(Application::class);
        $app->method('resolve')->willReturn($mockLoader);

        $provider = new RouterServiceProvider($app);
        $provider->boot();

        $hook = $this->findAction('rest_api_init');
        $hook[1]();
    }

    /**
     * The admin_menu closure must call loadAdminRoutes() on the RouteLoader.
     */
    public function testAdminMenuHookLoadsAdminRoutes(): void
    {
        $mockLoader = $this->createMock(RouteLoader::class);
        $mockLoader->expects($this->once())->method('loadAdminRoutes');

        $app = $this->createStub(Application::class);
        $app->method('resolve')->willReturn($mockLoader);

        $provider = new RouterServiceProvider($app);
        $provider->boot();

        $hook = $this->findAction('admin_menu');
        $hook[1]();
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Find the recorded add_action() call for the given WordPress hook.
     *
     * @param string $hookName WordPress hook name (e.g. 'rest_api_init').
     * @return array<int, mixed>|null The recorded arguments or null when not found.
     */
    private function findAction(string $hookName): ?array
    {
        foreach (WordPressRuntime::$actions as $action) {
            if ($action[0] === $hookName) {
                return $action;
            }
        }

        return null;
    }
}
