<?php

/**
 * Part of Omega - Routing Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Routing;

use Omega\Container\ServiceProvider;
use ReflectionException;

use function add_action;

/**
 * Registers and bootstraps application routing for both REST API and admin areas.
 *
 * This service provider integrates the framework routing layer with WordPress lifecycle hooks,
 * automatically loading route definition files during the appropriate execution phases.
 *
 * It binds the RouterBuilder into the service container as a singleton and ensures that:
 * - API routes are loaded during `rest_api_init`
 * - Admin routes are loaded during `admin_menu`
 *
 * Route definitions are loaded from both the default route files and any additional
 * files registered by the application container.
 *
 * This provider acts as the bridge between the Omega routing system and WordPress execution flow.
 *
 * @category  Omega
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class RouterServiceProvider extends ServiceProvider
{
    #region Container
    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->app->singleton('router', function ($app) {
            return new RouterBuilder($app);
        });

        $this->app->singleton(RouteLoader::class, function ($app) {
            return new RouteLoader($app);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException
     */
    public function boot(): void
    {
        add_action('rest_api_init', function () {
            $this->app->resolve(RouteLoader::class)->loadRestRoutes();
        });

        add_action('admin_menu', function () {
            $this->app->resolve(RouteLoader::class)->loadAdminRoutes();
        }, 99);
    }
    #endregion
}
