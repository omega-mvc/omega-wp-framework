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

use Omega\Application\ApplicationInterface;

use function file_exists;


/**
 * RouteLoader
 *
 * Responsible for loading application route definition files.
 *
 * This class provides a centralized mechanism for loading REST API and
 * administrative route files registered by the application. It resolves
 * framework default route locations together with additional route files
 * provided by the application or installed packages.
 *
 * Route files are loaded only when they exist on the filesystem and are
 * included once to prevent duplicate route registration.
 *
 * The loader depends on the application instance to determine the base path
 * and retrieve dynamically registered route file collections.
 *
 * @category  Omega
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
readonly class RouteLoader
{
	#region Lifecycle
	/**
	 * Create a new route loader instance.
	 *
	 * The application instance is used to resolve the framework base path
	 * and access additional route files registered by the application.
	 *
	 * @param ApplicationInterface $app Application instance providing runtime paths
	 *                                  and registered route files.
	 */
	public function __construct( private ApplicationInterface $app ) {
	}
	#endregion

	#region Route Loading
	/**
	 * Load REST API route definition files.
	 *
	 * Loads the framework default REST routes file and all additional REST
	 * route files registered by the application.
	 *
	 * The default route file is resolved from the application's base path:
	 *
	 * routes/api.php
	 *
	 * @return void
	 */
	public function loadRestRoutes(): void {
		$this->load( [
			$this->app->getBasePath() . '/routes/api.php',
			...$this->app->getRestRouteFiles()
		] );
	}

	/**
	 * Load administrative route definition files.
	 *
	 * Loads the framework default administration routes file and all additional
	 * administrative route files registered by the application.
	 *
	 * The default route file is resolved from the application's base path:
	 *
	 * routes/admin.php
	 *
	 * @return void
	 */
	public function loadAdminRoutes(): void {
		$this->load( [
			$this->app->getBasePath() . '/routes/admin.php',
			...$this->app->getAdminRouteFiles()
		] );
	}
	#endregion

	#region Internal Helpers
	/**
	 * Load route files from the given list of filesystem paths.
	 *
	 * Each route file is checked before loading to ensure that only existing
	 * files are included. Files are loaded using require_once to prevent
	 * duplicate execution during the application lifecycle.
	 *
	 * @param array<int, string> $files List of route file paths to load.
	 *
	 * @return void
	 */
	private function load( array $files ): void {
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
	#endregion
}