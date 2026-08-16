<?php

/**
 * Part of Omega - Admin Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Admin;

use Omega\Admin\Features\FeaturesInterface;
use Omega\Admin\Features\WooCommerce;
use Omega\Admin\Menu\AbstractMenuBuilder;
use Omega\Container\ServiceProvider;
use ReflectionException;

use function add_action;
use function class_exists;

/**
 * Service provider responsible for bootstrapping Omega admin features.
 *
 * Registers admin-related services and configures admin setup and menu
 * builders from application configuration.
 *
 * @category  Omega
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class AdminServiceProvider extends ServiceProvider
{
    /** @var array<class-string<FeaturesInterface>> Registered admin feature integrations. */
    private array $features = [
        WooCommerce::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('admin.manager', AdminManager::class);
        foreach ($this->features as $feature) {
            $this->app->singleton($feature, fn () => new $feature($this->app));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException
     */
    public function boot(): void
    {
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_init', [$this, 'adminSetup']);

        foreach ($this->features as $feature) {
            $this->app->resolve($feature)->init();
        }

        $this->app->resolve('admin.manager')->init();
    }

    /**
     * Initialize the configured admin setup class.
     *
     * Resolves the setup class from configuration and instantiates it if available.
     *
     * @return void
     * @throws ReflectionException Thrown when a configured class cannot be resolved or instantiated.
     */
    public function adminSetup(): void
    {
        $setupClass = $this->app->resolve('config')->string('app.admin.setup');

        if (!class_exists($setupClass)) {
            return;
        }

        $setup = new $setupClass();
    }

    /**
     * Register and create the configured admin menu structure.
     *
     * Resolves the menu builder class from configuration and builds
     * the WordPress admin navigation structure.
     *
     * @return void
     * @throws ReflectionException Thrown when a configured class cannot be resolved or instantiated.
     */
    public function adminMenu(): void
    {
        $menuClass = $this->app->resolve('config')->string('app.admin.menu');

        if (!class_exists($menuClass)) {
            return;
        }

        /** @var AbstractMenuBuilder $adminMenu **/
        $adminMenu = new $menuClass($this->app);
        $adminMenu->register();
        $adminMenu->create();
    }
}
