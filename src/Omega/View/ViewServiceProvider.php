<?php

/**
 * Part of Omega - View Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\View;

use Omega\Container\ServiceProvider;

/**
 * Register the view rendering service within the application container.
 *
 * This service provider is responsible for binding the framework's
 * view renderer into the dependency injection container as a singleton.
 *
 * The registered instance can later be resolved through the container
 * or accessed through the corresponding facade, allowing templates
 * to be rendered from anywhere inside the application.
 *
 * The view service is shared as a singleton because the renderer
 * only needs access to the current application instance and does
 * not maintain per-request mutable state.
 *
 * @category  Omega
 * @package   View
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class ViewServiceProvider extends ServiceProvider
{
	#region Container
    /**
     * Register the view service in the container.
     *
     * This method binds the {@see View} class to the container using
     * the "view" service key so that only one instance is created
     * and reused during the application lifecycle.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('view', function () {
            return new View($this->app);
        });
    }
	#endregion
}
