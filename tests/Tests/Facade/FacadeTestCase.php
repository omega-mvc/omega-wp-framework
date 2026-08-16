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

use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use Omega\Facade\AbstractFacade;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\FixturesPathTrait;

/**
 * Base test case for Facade package tests that resolve container services.
 *
 * Registers a single application in the shared factory registry so facade
 * proxies can be exercised in a plain PHPUnit process, and resets the facade
 * instance cache before and after every test to avoid cross-test pollution.
 *
 * @category  Tests
 * @package   Facade
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class FacadeTestCase extends TestCase
{
    use FixturesPathTrait;

    /**
     * Register a single application in the shared factory registry and reset
     * the facade resolved-instance cache.
     */
    protected function setUp(): void
    {
        parent::setUp();

        AbstractFacade::clearResolvedInstances();

        $this->setFactoryApps(['plugin' => $this->makeApplication()]);
    }

    /**
     * Clear the shared factory registry and the facade instance cache.
     */
    protected function tearDown(): void
    {
        AbstractFacade::clearResolvedInstances();

        $this->setFactoryApps([]);

        parent::tearDown();
    }

    /**
     * Build an application backed by the plugin fixture.
     *
     * @return Application Application instance
     */
    protected function makeApplication(): Application
    {
        return new Application('plugin', $this->pluginBasePath());
    }

    /**
     * Base path of the plugin fixture.
     *
     * @return string Absolute plugin fixture path
     */
    protected function pluginBasePath(): string
    {
        return $this->setFixturePath('/fixtures/app/plugin/sample');
    }

    /**
     * Read the current facade resolved-instance cache.
     *
     * @return array<string, mixed> Cached instances keyed by accessor
     */
    protected function resolvedInstances(): array
    {
        $property = new ReflectionProperty(AbstractFacade::class, 'resolvedInstance');

        return $property->getValue(null);
    }

    /**
     * Overwrite the facade resolved-instance cache.
     *
     * @param array<string, mixed> $instances Cached instances keyed by accessor
     */
    protected function setResolvedInstances(array $instances): void
    {
        $property = new ReflectionProperty(AbstractFacade::class, 'resolvedInstance');
        $property->setValue(null, $instances);
    }

    /**
     * Inject the given applications into the shared factory registry.
     *
     * @param array<string, Application> $apps Applications keyed by id
     */
    protected function setFactoryApps(array $apps): void
    {
        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, $apps);
    }
}
