<?php

/**
 * Part of Omega - Tests Settings Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Settings;

use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\FixturesPathTrait;
use Tests\Routing\WordPressRuntime;

/**
 * Base test case for Settings package tests that resolve container services.
 *
 * Registers a single application in the shared factory registry and clears
 * the WordPress option stub state so each test starts from a clean slate.
 *
 * @category  Tests
 * @package   Settings
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class SettingsTestCase extends TestCase
{
    use FixturesPathTrait;

    /**
     * Register a single application and reset the option stub state.
     */
    protected function setUp(): void
    {
        parent::setUp();

        WordPressRuntime::reset();
        $this->setFactoryApps(['plugin' => $this->makeApplication()]);
    }

    /**
     * Clear the shared factory registry and the option stub state.
     */
    protected function tearDown(): void
    {
        $this->setFactoryApps([]);
        WordPressRuntime::reset();

        parent::tearDown();
    }

    /**
     * Build an application backed by the plugin fixture.
     *
     * @return Application Application instance
     */
    protected function makeApplication(): Application
    {
        return new Application('plugin', $this->setFixturePath('/fixtures/app/plugin/sample'));
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
