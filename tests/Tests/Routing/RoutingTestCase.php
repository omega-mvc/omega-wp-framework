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

use Omega\Admin\AdminManager;
use Omega\Application\Application;
use Omega\Routing\Router;
use Omega\Routing\RouterBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for the Routing package.
 *
 * Loads the WordPress runtime stubs, resets the shared registry between
 * tests and offers factories to build routers backed by mocked apps.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class RoutingTestCase extends TestCase
{
    /**
     * Loads the WP stubs and clears the recorded calls before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        WordPressRuntime::reset();
    }

    /**
     * Builds a Router backed by a mocked application and an empty builder.
     *
     * @return Router Router instance ready to register REST routes
     */
    protected function makeRouter(): Router
    {
        $app = $this->createStub(Application::class);

        return new Router(new RouterBuilder($app));
    }

    /**
     * Builds a RouterBuilder whose admin manager lookup is mocked.
     *
     * @return RouterBuilder Builder with a mocked AdminManager behind 'admin.manager'
     */
    protected function makeBuilder(): RouterBuilder
    {
        $adminManager = $this->createStub(AdminManager::class);
        $app = $this->createMock(Application::class);
        $app->expects($this->once())
            ->method('resolve')
            ->with('admin.manager')
            ->willReturn($adminManager);

        return new RouterBuilder($app);
    }
}
