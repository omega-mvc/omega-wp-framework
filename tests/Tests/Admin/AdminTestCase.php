<?php

/**
 * Part of Omega - Tests Admin Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Admin;

use Omega\Admin\AdminServiceProvider;
use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use Omega\Config\ConfigRepository;
use Omega\Facade\AbstractFacade;
use ReflectionProperty;
use Tests\Database\DatabaseTestCase;

use function Omega\Application\slash;

/**
 * Base test case for the Admin package.
 *
 * The Admin service provider is not registered automatically on the CLI, so it
 * is registered explicitly on a single plugin application. The application is
 * the only entry of the shared factory registry, which keeps the facade
 * proxies resolving services from the application under test.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class AdminTestCase extends DatabaseTestCase
{
    /** Application instance owning the Admin service provider. */
    protected Application $app;

    /** Admin service provider registered on the application. */
    protected AdminServiceProvider $provider;

    /**
     * Boot a plugin application with the Admin service provider registered.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        AbstractFacade::clearResolvedInstances();

        $this->app = new Application('plugin', $this->pluginBasePath());
        $this->setFactoryApps(['plugin' => $this->app]);

        $this->provider = new AdminServiceProvider($this->app);
        $this->app->register($this->provider);
    }

    /**
     * Drop the application, the provider and the cached facade instances.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        AbstractFacade::clearResolvedInstances();
        $this->setFactoryApps([]);
        unset($_GET['page']);

        parent::tearDown();
    }

    /**
     * Base path of the plugin fixture used by the Admin tests.
     *
     * @return string Absolute plugin fixture path
     */
    protected function pluginBasePath(): string
    {
        return slash(path: __DIR__ . '/../fixtures/app/plugin/sample');
    }

    /**
     * Replace the config service with a repository built from the given array.
     *
     * @param array<string, mixed> $config Configuration values keyed by file name
     * @return ConfigRepository The config repository bound to the application
     */
    protected function bindConfig(array $config): ConfigRepository
    {
        $repository = new ConfigRepository($config);
        $this->app->bindInstance('config', $repository);

        return $repository;
    }

    /**
     * Inject the given applications into the shared factory registry.
     *
     * @param array<string, Application> $apps Applications keyed by id
     * @return void
     */
    protected function setFactoryApps(array $apps): void
    {
        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, $apps);
    }
}
