<?php

/**
 * Part of Omega - Tests Http Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Http;

use Omega\Http\Facade\Response as ResponseFacade;
use Omega\Http\Response as HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\Support\WPRestResponse;

/**
 * Tests the Http Response facade static proxy.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ResponseFacade::class)]
final class ResponseFacadeTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ResponseFacade::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        ResponseFacade::clearResolvedInstances();

        parent::tearDown();
    }

    /**
     * Test the facade exposes the Response service accessor.
     */
    public function testReturnsFacadeAccessor(): void
    {
        $this->assertSame(HttpResponse::class, ResponseFacade::getFacadeAccessor());
    }

    /**
     * Test the facade proxies a static call to the container service.
     */
    public function testProxiesJsonCallToContainerService(): void
    {
        $app = $this->makeApplication();
        $app->bindInstance(HttpResponse::class, new HttpResponse());
        $this->setFactoryApps(['app' => $app]);

        $result = ResponseFacade::json(['ok' => true], 200);

        $this->assertInstanceOf(WPRestResponse::class, $result);
        $this->assertSame(['ok' => true], $result->get_data());
    }
}
