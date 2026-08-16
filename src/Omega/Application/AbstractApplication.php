<?php

/**
 * Part of Omega - Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Application;

use Omega\Admin\AdminServiceProvider;
use Omega\Config\ConfigServiceProvider;
use Omega\Container\Container;
use Omega\Container\ContainerInterface;
use Omega\Database\DatabaseServiceProvider;
use Omega\Localization\LocalizationServiceProvider;
use Omega\Routing\RouterServiceProvider;
use Omega\Settings\SettingsServiceProvider;
use Omega\View\ViewServiceProvider;

use function array_filter;
use function array_map;
use function file_exists;
use function get_class;
use function in_array;
use function is_array;
use function is_string;
use function method_exists;

/**
 * Core application container responsible for bootstrapping and managing services.
 *
 * Handles service provider registration, routing, migrations, and integration
 * with the WordPress plugin lifecycle.
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class AbstractApplication extends Container implements ApplicationInterface
{
    #region Properties
    /** @var array Registered service provider instances. */
    protected array $serviceProviders = [];

    /** @var array<class-string> Service providers defined by the application layer. */
    protected array $providers = [];
    #endregion

    #region Lifecycle
    /**
     * Create and initialize a new application instance.
     *
     * This constructor is responsible for setting up the core state of the application,
     * validating the required initialization parameters, and triggering the registration
     * of all fundamental framework components.
     *
     * It performs the following steps in order:
     * - Validates the provided application identifier and base path
     * - Assigns the application ID
     * - Initializes the base path of the application
     * - Defines the application root directory
     * - Registers core container bindings
     * - Registers base framework service providers
     * - Registers user-defined service providers
     * - Registers core container aliases
     *
     * The application instance is fully bootstrapped at the end of this process,
     * meaning that all core services are available for resolution and use.
     *
     * @param string $id Unique identifier of the application instance.
     *                   This value is used to distinguish between multiple
     *                   applications within the same runtime environment.
     * @param string $basePath Absolute path to the root directory of the application.
     *                         This path is used as the base for configuration,
     *                         service discovery, and file resolution.
     * @return void
     */
    public function __construct(string $id, string $basePath)
    {
        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerServiceProviders();
        $this->registerCoreContainerAliases();
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap(): void
    {
        array_map(
            fn($provider) => $provider->boot(),
            array_filter($this->serviceProviders, fn($provider) => method_exists($provider, 'boot'))
        );
    }
    #endregion

    #region Service providers
    /**
     * Register core container bindings required by the application.
     *
     * @return void
     */
    protected function registerBaseBindings(): void
    {
        $this->bindInstance(ContainerInterface::class, $this);
        $this->bindInstance(Container::class, $this);
        $this->bindInstance(ApplicationInterface::class, $this);
    }

    /**
     * Register the default framework service providers.
     *
     * @return void
     */
    protected function registerBaseServiceProviders(): void
    {
        $this->register(new ConfigServiceProvider($this));
        $this->register(new SettingsServiceProvider($this));
        $this->register(new RouterServiceProvider($this));
        $this->register(new DatabaseServiceProvider($this));
        if (!$this->isCli()) {
            $this->register(new ViewServiceProvider($this));
            $this->register(new LocalizationServiceProvider($this));
            $this->register(new AdminServiceProvider($this));
        }
    }

    /**
     * Register user-defined service providers from configuration file.
     *
     * @return void
     */
    protected function registerServiceProviders(): void
    {
        $providersFile = $this->getBasePath() . '/config/providers.php';
        if (file_exists($providersFile)) {
            $providers = include $providersFile;
            if (is_array($providers)) {
                array_map(fn($provider) => $this->register($provider), $providers);
            }
        }
    }
    #endregion

    #region Container
    /**
     * {@inheritdoc}
     */
    public function register(object|string $provider): object|string
    {
        $class = is_string($provider) ? $provider : get_class($provider);

        if (isset($this->serviceProviders[$class])) {
            return $this->serviceProviders[$class];
        }

        $instance = $this->newProviderInstance($provider);

        $this->serviceProviders[$class] = $instance;

        if (method_exists($instance, 'register')) {
            $instance->register();
        }

        return $instance;
    }

    /**
     * Resolve a service provider instance from a class-string or an existing object.
     *
     * @param object|string $provider Service provider class name or instance.
     * @return object Resolved service provider instance.
     */
    private function newProviderInstance(object|string $provider): object
    {
        return is_string($provider) ? new $provider($this) : $provider;
    }

    /**
     * Register core container aliases for internal services.
     *
     * @return void
     */
    protected function registerCoreContainerAliases(): void
    {
    }
    #endregion

    #region Questo metodo è provvisorio.
    protected function isCli(): bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
    }
    #endregion
}
