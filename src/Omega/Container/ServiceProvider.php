<?php

/**
 * Part of Omega - Container Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Container;

use Omega\Application\ApplicationInterface;

/**
 * Base service provider class.
 *
 * Service providers are responsible for registering bindings, services, and
 * application components into the container, as well as bootstrapping logic
 * after all services have been registered.
 *
 * This class provides a structured way to organize application setup,
 * separating service registration from runtime initialization.
 *
 * It also includes helper methods for loading framework-specific resources
 * such as routes and database migrations.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class ServiceProvider extends AbstractServiceProvider
{
	#region Lifecycle
    /**
     * Create a new service provider instance.
     *
     * @param ApplicationInterface $app The application instance used to access the container and core services.
     * @return void
     */
    public function __construct(public ApplicationInterface $app)
    {
		parent::__construct($app);
    }
	#endregion

	#region Resources
    /**
     * Load route definitions from the given file.
     *
     * @param string $path The path to the route file.
     * @param string $type The route group type (e.g. "api", "web").
     * @return void
     */
    public function loadRoutesFrom(string $path, string $type = 'api'): void
    {
        $this->app->addRouteFile($path, $type);
    }

    /**
     * Register a directory containing migration files.
     *
     * @param string $path The path to the migrations' directory.
     * @return void
     */
    public function loadMigrationsFrom(string $path): void
    {
        $this->app->addMigrationFolder($path);
    }
	#endregion
}
