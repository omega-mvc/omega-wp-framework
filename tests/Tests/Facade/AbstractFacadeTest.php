<?php

/**
 * Part of Omega - Tests Facade Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Facade;

use Omega\Config\ConfigRepository;
use Omega\Config\Facades\Config as ConfigFacade;
use Omega\Facade\AbstractFacade;
use Omega\Facade\Exception\FacadeObjectNotSetException;
use Omega\Facade\FacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Tests the AbstractFacade static proxy behaviour.
 *
 * @category  Tests
 * @package   Facade
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractFacade::class)]
#[CoversClass(FacadeObjectNotSetException::class)]
final class AbstractFacadeTest extends FacadeTestCase
{
    /**
     * Test concrete facades honour the accessor contract.
     */
    public function testConcreteFacadeImplementsContract(): void
    {
        $this->assertTrue(is_a(ConfigFacade::class, FacadeInterface::class, true));
        $this->assertSame('config', ConfigFacade::getFacadeAccessor());
    }

    /**
     * Test static calls are proxied to the container service.
     */
    public function testProxiesStaticCallsToContainerService(): void
    {
        $this->assertSame('Sample Plugin', ConfigFacade::get('app.name'));
    }

    /**
     * Test the facade root resolves the container-bound service.
     */
    public function testGetFacadeRootResolvesContainerService(): void
    {
        $this->assertInstanceOf(ConfigRepository::class, ConfigFacade::getFacadeRoot());
    }

    /**
     * Test the resolved instance is cached after the first resolution.
     */
    public function testResolvedInstanceIsCached(): void
    {
        $this->assertSame(ConfigFacade::getFacadeRoot(), ConfigFacade::getFacadeRoot());
        $this->assertArrayHasKey('config', $this->resolvedInstances());
    }

    /**
     * Test a single cached instance can be cleared.
     */
    public function testClearResolvedInstanceRemovesOnlyMatchingEntry(): void
    {
        $configRoot = ConfigFacade::getFacadeRoot();

        $this->setResolvedInstances(['config' => $configRoot, 'settings' => $configRoot]);

        AbstractFacade::clearResolvedInstance('config');

        $this->assertSame(['settings' => $configRoot], $this->resolvedInstances());
    }

    /**
     * Test all cached instances can be cleared at once.
     */
    public function testClearResolvedInstancesEmptiesTheCache(): void
    {
        $configRoot = ConfigFacade::getFacadeRoot();

        $this->setResolvedInstances(['config' => $configRoot, 'settings' => $configRoot]);

        AbstractFacade::clearResolvedInstances();

        $this->assertSame([], $this->resolvedInstances());
    }

    /**
     * Test a static proxy call throws when the facade root is falsy.
     */
    public function testCallStaticThrowsWhenFacadeRootIsNotSet(): void
    {
        $this->setResolvedInstances(['config' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A facade root has not been set.');

        ConfigFacade::get('app.name');
    }

    /**
     * Test the base accessor throws when a concrete facade omits it.
     */
    public function testGetFacadeAccessorThrowsWhenNotImplemented(): void
    {
        $facade = new class extends AbstractFacade {
        };

        $this->expectException(FacadeObjectNotSetException::class);
        $this->expectExceptionMessage('Facade does not define a facade accessor.');

        $facade::getFacadeAccessor();
    }

    /**
     * Test the facade root resolution throws when no accessor is defined.
     */
    public function testGetFacadeRootThrowsWhenAccessorNotImplemented(): void
    {
        $facade = new class extends AbstractFacade {
        };

        $this->expectException(FacadeObjectNotSetException::class);

        $facade::getFacadeRoot();
    }

    /**
     * Test the facade exception is a standard runtime exception.
     */
    public function testFacadeObjectNotSetExceptionExtendsRuntimeException(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new FacadeObjectNotSetException());
    }
}
