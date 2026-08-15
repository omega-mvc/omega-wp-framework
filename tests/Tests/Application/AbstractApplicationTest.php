<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Application;

use Omega\Admin\AdminManager;
use Omega\Application\AbstractApplication;
use Omega\Application\Application;
use Omega\Application\ApplicationInterface;
use Omega\Config\ConfigRepository;
use Omega\Container\Container;
use Omega\Container\ContainerInterface;
use Omega\Container\Exceptions\ClassNotFoundException;
use Omega\Database\Database;
use Omega\Database\Migrations\Migrator;
use Omega\Routing\RouteLoader;
use Omega\Routing\RouterBuilder;
use Omega\Settings\SettingsRepository;
use Omega\View\View;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Tests\Application\Support\AbstractApplicationStub;
use Tests\Application\Support\FakeProvider;
use Tests\Application\Support\PlainProvider;

/**
 * Tests the AbstractApplication kernel behavior.
 *
 * Drives the abstract kernel class through a concrete stub and covers every
 * method at 100% line, branch, and path coverage: container bindings, service
 * provider registration and bootstrapping, CLI detection, and the config file
 * loading path.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractApplication::class)]
final class AbstractApplicationTest extends ApplicationTestCase
{
    /**
     * Test the core container bindings and base services are registered.
     */
    public function testRegistersCoreBindingsAndBaseServicesInCliMode(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $this->assertSame($app, $app->resolve(ContainerInterface::class));
        $this->assertSame($app, $app->resolve(Container::class));
        $this->assertSame($app, $app->resolve(ApplicationInterface::class));

        $this->assertInstanceOf(ConfigRepository::class, $app->resolve('config'));
        $this->assertInstanceOf(SettingsRepository::class, $app->resolve('settings'));
        $this->assertInstanceOf(RouterBuilder::class, $app->resolve('router'));
        $this->assertInstanceOf(RouteLoader::class, $app->resolve(RouteLoader::class));
        $this->assertInstanceOf(Database::class, $app->resolve('database'));
        $this->assertInstanceOf(Migrator::class, $app->resolve('migrator'));
    }

    /**
     * Test the view service is not registered in CLI mode.
     */
    public function testCliModeSkipsViewAndAdminServices(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $this->expectException(ClassNotFoundException::class);

        $app->resolve('view');
    }

    /**
     * Test the view and admin services are registered outside CLI mode.
     */
    public function testNonCliModeRegistersViewAndAdminServices(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath(), false);

        $this->assertInstanceOf(View::class, $app->resolve('view'));
        $this->assertInstanceOf(AdminManager::class, $app->resolve('admin.manager'));
    }

    /**
     * Test the kernel CLI detection matches the current process SAPI.
     */
    public function testIsCliDetectsCurrentProcessSapi(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $method = new ReflectionMethod(AbstractApplication::class, 'isCli');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($app));
    }

    /**
     * Test providers declared in the config file are registered on boot.
     */
    public function testRegistersProvidersDeclaredInConfigFile(): void
    {
        $app = new AbstractApplicationStub('sample', $this->pluginBasePath());

        $this->assertSame(1, FakeProvider::$registerCalls);
        $this->assertSame('fake', $app->resolve('fake.service'));
    }

    /**
     * Test a providers file that does not return an array is ignored.
     */
    public function testIgnoresProvidersFileWithoutArrayReturn(): void
    {
        $app = new AbstractApplicationStub('sample', $this->nonArrayProvidersBasePath());

        $this->assertSame(0, FakeProvider::$registerCalls);
        $this->assertInstanceOf(AbstractApplication::class, $app);
    }

    /**
     * Test bootstrap() only boots providers that expose a boot() method.
     */
    public function testBootstrapBootsOnlyProvidersExposingBootMethod(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $app->register(FakeProvider::class);
        $app->register(PlainProvider::class);

        $app->bootstrap();

        $this->assertSame(1, FakeProvider::$bootCalls);
    }

    /**
     * Test a class-string provider is instantiated and registered once.
     */
    public function testRegisterWithStringProviderIsInstantiatedOnce(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $provider = $app->register(FakeProvider::class);

        $this->assertInstanceOf(FakeProvider::class, $provider);
        $this->assertSame(1, FakeProvider::$registerCalls);

        $this->assertSame($provider, $app->register(FakeProvider::class));
        $this->assertSame(1, FakeProvider::$registerCalls);
    }

    /**
     * Test a provider instance is stored and registered once.
     */
    public function testRegisterWithInstanceProviderIsStoredAsIs(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $instance = new FakeProvider($app);

        $this->assertSame($instance, $app->register($instance));
        $this->assertSame(1, FakeProvider::$registerCalls);

        $this->assertSame($instance, $app->register($instance));
        $this->assertSame(1, FakeProvider::$registerCalls);
    }

    /**
     * Test a provider instance without lifecycle methods is accepted and skipped.
     */
    public function testRegisterAcceptsStringProviderWithoutLifecycleMethods(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $provider = $app->register(PlainProvider::class);

        $this->assertInstanceOf(PlainProvider::class, $provider);
    }

    /**
     * Test a provider instance without lifecycle methods is stored as-is.
     */
    public function testRegisterAcceptsInstanceProviderWithoutLifecycleMethods(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $instance = new PlainProvider();

        $this->assertSame($instance, $app->register($instance));
    }

    /**
     * Base path of the fixture whose providers file does not return an array.
     *
     * @return string Absolute fixture path
     */
    private function nonArrayProvidersBasePath(): string
    {
        return $this->setFixturePath('/fixtures/app/providers-nonarray');
    }
}
