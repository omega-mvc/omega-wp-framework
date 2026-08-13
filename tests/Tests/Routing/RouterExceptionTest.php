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
use Tests\Routing\Support\ThrowingController;
use Tests\Routing\Support\WPError;
use Tests\Routing\Support\WPRestRequest;

/**
 * Tests the Router error handling behavior.
 *
 * Server-side exceptions must be translated into a generic client-facing
 * error without leaking the internal exception message.
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
final class RouterExceptionTest extends RoutingTestCase
{
    /**
     * A throwing controller must not leak its message to the client.
     */
    public function testServerErrorsDoNotLeakExceptionMessages(): void
    {
        $router = $this->makeRouter();

        $router->addRoute('GET', '/secret', [ThrowingController::class, 'index']);

        $callback = WordPressRuntime::$restRoutes[0][2]['callback'];
        $result = $callback(new WPRestRequest());

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertNotSame('secret-internal-detail', $result->getErrorMessage());
    }
}
