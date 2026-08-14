<?php

/**
 * Part of Omega - Tests View Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\View;

use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use Omega\View\Facade\View as ViewFacade;
use Omega\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\FixturesPathTrait;

/**
 * Tests the View facade static proxy.
 *
 * @category  Tests
 * @package   View
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ViewFacade::class)]
final class ViewFacadeTest extends TestCase
{
    use FixturesPathTrait;

    /**
     * Clear the facade cache and the application registry before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        ViewFacade::clearResolvedInstances();
        $this->setFactoryApps([]);
    }

    protected function tearDown(): void
    {
        $this->setFactoryApps([]);
        ViewFacade::clearResolvedInstances();

        parent::tearDown();
    }

    /**
     * Test the facade proxies a static call to the container service.
     */
    public function testProxiesRenderCallToContainerService(): void
    {
        $app = new Application('theme', $this->setFixturePath('/fixtures/app/theme'));
        (new ViewServiceProvider($app))->register();
        $this->setFactoryApps(['theme' => $app]);

        $this->assertSame('Hello, Omega!', ViewFacade::render('welcome', ['name' => 'Omega']));
    }

    /**
     * Test the facade exposes the "view" accessor.
     */
    public function testReturnsFacadeAccessor(): void
    {
        $this->assertSame('view', ViewFacade::getFacadeAccessor());
    }

    /**
     * Inject the given applications into the shared factory registry.
     *
     * @param array<string, Application> $apps Applications keyed by id
     */
    private function setFactoryApps(array $apps): void
    {
        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, $apps);
    }
}
