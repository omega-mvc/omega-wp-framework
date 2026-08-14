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

use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\FixturesPathTrait;

/**
 * Base test case for Config package tests that resolve container services.
 *
 * Registers a single application in the shared factory registry so the
 * config facade and provider can be exercised in a plain PHPUnit process.
 *
 * @category  Tests
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class ConfigTestCase extends TestCase
{
    use FixturesPathTrait;

    /**
     * Register a single application in the shared factory registry.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setFactoryApps(['plugin' => $this->makeApplication()]);
    }

    /**
     * Clear the shared factory registry.
     */
    protected function tearDown(): void
    {
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
     * Base path of an application without any configuration directory.
     *
     * @return string Absolute fixture root path
     */
    protected function emptyBasePath(): string
    {
        return $this->setFixturePath('/fixtures/app');
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
