<?php

/**
 * Part of Omega - Tests Config Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Config;

use Omega\Config\Facades\Config as ConfigFacade;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the Config facade proxy.
 *
 * @category  Tests
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ConfigFacade::class)]
final class ConfigFacadeTest extends ConfigTestCase
{
    /**
     * Test the facade exposes the config accessor.
     */
    public function testReturnsFacadeAccessor(): void
    {
        $this->assertSame('config', ConfigFacade::getFacadeAccessor());
    }

    /**
     * Test static calls are proxied to the container service.
     */
    public function testProxiesGetCallToContainerService(): void
    {
        $this->assertSame('Sample Plugin', ConfigFacade::get('app.name'));
        $this->assertTrue(ConfigFacade::get('app.debug'));
    }
}
