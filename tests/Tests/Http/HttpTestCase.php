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

use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\FixturesPathTrait;
use Tests\Http\Support\FakeModel;

/**
 * Base test case for Http package tests that need a resolvable application.
 *
 * Registers a single application in the shared factory registry so that
 * model and facade resolution works inside a plain PHPUnit process.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class HttpTestCase extends TestCase
{
    use FixturesPathTrait;

    /**
     * Register a single application in the shared factory registry.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setFactoryApps(['app' => $this->makeApplication()]);
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
     * Build an application backed by the theme fixture.
     *
     * @return Application Application instance
     */
    protected function makeApplication(): Application
    {
        return new Application('app', $this->setFixturePath('/fixtures/app/theme'));
    }

    /**
     * Create a model carrying the given attributes.
     *
     * @param array<string, mixed> $attributes Model attributes
     * @return FakeModel Model instance
     */
    protected function makeModel(array $attributes): FakeModel
    {
        return new FakeModel($attributes);
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
