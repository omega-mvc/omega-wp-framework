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

use Omega\Config\ConfigRepository;
use Omega\Settings\SettingsRepository;

/**
 * Defines the contract for an Omega application instance.
 *
 * An application represents the runtime context of a WordPress plugin or
 * theme. It exposes the services and metadata required by the framework,
 * including application identity, filesystem paths, configuration access,
 * service registration, bootstrapping, routing, migrations, and header
 * information.
 *
 * Both plugin and theme implementations must provide a consistent API so
 * framework components can interact with the current application without
 * depending on its underlying WordPress type.
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
interface ApplicationInterface extends AbstractApplicationInterface
{
	#region Lifecycle
	/**
	 * Bootstrap all registered service providers.
	 *
	 * Calls the "boot" method on each provider if available.
	 *
	 * @return void
	 */
	public function bootstrap(): void;
	#endregion

	#region Identity
	/**
	 * Get the unique application identifier.
	 *
	 * @return string Return the application identifier.
	 */
	public function getId(): string;

	/**
	 * Get the application id in snake_case format.
	 *
	 * @return string|array Return the application in snake_case format.
	 */
	public function getIdAsUnderscore(): array|string;

	/**
	 * Get the name of the application framework.
	 *
	 * This value identifies the core framework instance and is independent
	 * from any plugin, theme, or application-specific metadata.
	 *
	 * @return string The framework name.
	 */
	public function getName(): string;

	/**
	 * Get the version of the application framework.
	 *
	 * This value represents the current version of the core framework
	 * and is independent of any plugin, theme, or application-specific versioning.
	 *
	 * @return string The framework version.
	 */
	public function getVersion(): string;
	#endregion

	#region Filesystem
	/**
	 * Get the base path of the application.
	 *
	 * @return string Return the absolute base path.
	 */
	public function getBasePath(): string;

	/**
	 * Get the root directory of the plugin.
	 *
	 * @return string Return the plugin root dir.
	 */
	public function getAppRoot(): string;

	/**
	 * Get the main plugin file path.
	 *
	 * @return string Return the plugin file.
	 */
	public function getAppFile(): string;
	#endregion

	#region Routes
	/**
	 * Add a route file to the application.
	 *
	 * @param string $path Path to the route file
	 * @param string $type Route type (e.g. "api" or "admin")
	 * @return void
	 */
	public function addRouteFile(string $path, string $type = 'api'): void;

	/**
	 * Get all registered API route file paths.
	 *
	 * @return array List of API route file paths
	 */
	public function getRestRouteFiles(): array;

	/**
	 * Get all registered admin route file paths.
	 *
	 * @return array List of admin route file paths
	 */
	public function getAdminRouteFiles(): array;
	#endregion

	#region Migrations
	/**
	 * Register a migration folder path.
	 *
	 * @param string $path Directory containing migration files
	 * @return void
	 */
	public function addMigrationFolder(string $path): void;

	/**
	 * Get all registered migration folder paths.
	 *
	 * @return array List of migration directories
	 */
	public function getMigrationFolders(): array;
	#endregion

	#region Configuration
	/**
	 * Get the configuration repository instance.
	 *
	 * @return ConfigRepository Configuration service instance
	 */
	public function config(): ConfigRepository;

	/**
	 * Get the settings repository instance.
	 *
	 * @return SettingsRepository Settings service instance
	 */
	public function settings(): SettingsRepository;

    /**
     * Retrieve a metadata value from the application header.
     *
     * This method provides a unified way to access metadata information
     * defined in the application's main entry file (for plugins) or
     * stylesheet header (for themes).
     *
     * The concrete implementation depends on the application type:
     * - Plugin applications typically read headers from the main plugin file
     *   using WordPress' `get_file_data()` function.
     * - Theme applications retrieve header values using the `WP_Theme` API
     *   or related WordPress theme metadata functions.
     *
     * This abstraction ensures a consistent API for accessing application
     * metadata regardless of whether the underlying implementation is a
     * WordPress plugin or theme.
     *
     * @param string $headerKey The name of the header field to retrieve
     *                      (e.g. "Version", "Author", "Text Domain").
     * @return string The value of the requested header field.
     *               Returns an empty string if the field does not exist
     *               or cannot be resolved.
     */
    public function getHeaderField(string $headerKey): string;
	#endregion

	#region Helpers
	/**
	 * Get application (bootstrapper) cache path.
	 *
	 * default './boostrap/cache/'.
	 *
	 * @return string Absolute path to the application bootstrap cache directory.
	 */
	public function getApplicationCachePath(): string;

	/**
	 * Detect application environment.
	 *
	 * @return string Current application environment (e.g. "dev", "prod").
	 */
	public function getEnvironment(): string;

	/**
	 * Detect application debug enable.
	 *
	 * @return bool True when application debug mode is enabled.
	 */
	public function isDebugMode(): bool;
	#endregion
}
