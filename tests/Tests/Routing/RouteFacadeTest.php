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

use Omega\Routing\Facade\Route;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the Route facade accessor.
 *
 * Locks the container key used by the Route facade to resolve the
 * underlying RouterBuilder instance.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Route::class)]
final class RouteFacadeTest extends RoutingTestCase
{
    /**
     * The facade accessor must resolve to the 'router' container key.
     */
    public function testGetFacadeAccessorReturnsRouterKey(): void
    {
        $this->assertSame('router', Route::getFacadeAccessor());
    }
}
