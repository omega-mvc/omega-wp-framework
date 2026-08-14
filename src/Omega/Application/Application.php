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

/** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection */

declare(strict_types=1);

namespace Omega\Application;

use Omega\Application\Exception\MissingParameterException;
use Omega\Config\ConfigRepository;
use Omega\Settings\SettingsRepository;
use Omega\Str\Str;
use ReflectionException;

use function array_filter;
use function array_map;
use function array_values;
use function rtrim;
use const DIRECTORY_SEPARATOR;

/**
 * Represents a concrete Omega application instance.
 *
 * This class extends the kernel application layer by providing application-specific
 * functionality required by Omega applications running on top of the kernel runtime.
 *
 * The Application class is responsible for managing application identity, filesystem
 * paths, routes, migrations, configuration access, and other services exposed to the
 * application layer.
 *
 * The lifecycle management and service provider bootstrapping process are delegated
 * to the parent kernel application class. This class only defines the application
 * domain behavior and acts as the bridge between the kernel runtime and concrete
 * application implementations.
 *
 * Concrete applications such as plugins or themes may extend this class to provide
 * environment-specific behavior while keeping the common application logic intact.
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Application extends AbstractApplication
{
    #region Properties
    /** @var string Base path of the application. */
    protected string $basePath;

    /** @var array Route file definitions grouped by type. */
    protected array $routeFiles = [];

    /** @var array Registered migration folder paths. */
    protected array $migrationFolders = [];

    /** @var string Root directory of the plugin. */
    protected string $appRoot = '';

    /** @var string Unique application identifier. */
    protected string $id;
    #endregion

    #region Lifecycle
    /**
     * Create a new Omega application instance.
     *
     * Initializes the concrete application layer by validating the required
     * application parameters, defining the application identity, and preparing
     * the filesystem paths used by the application runtime.
     *
     * The constructor performs the following operations:
     * - Validates the application identifier and base path.
     * - Stores the unique application identifier.
     * - Normalizes and assigns the application base path.
     * - Resolves the application root directory.
     * - Delegates kernel-level initialization to the parent application layer.
     *
     * The application lifecycle, container initialization, and service provider
     * registration are handled by the parent kernel application implementation.
     * This class is responsible only for application-specific state and behavior.
     *
     * Concrete implementations such as plugins and themes may extend this class
     * to provide environment-specific features while preserving the common
     * application initialization flow.
     *
     * @param string $id
     *        Unique identifier of the application instance.
     *        This value is used to resolve application-specific resources,
     *        configuration, and entry files.
     * @param string $basePath
     *        Absolute path to the application root directory.
     *        This path is used as the base location for routes, migrations,
     *        configuration files, and other application resources.
     * @return void
     * @throws MissingParameterException Thrown when the application identifier or base path is empty.
     */
    public function __construct(string $id, string $basePath)
    {
        $this->isValidData($id, $basePath);

        $this->id = $id;

        $basePath = rtrim(
            slash($basePath),
            DIRECTORY_SEPARATOR
        );

        $this->setBasePath($basePath);
        $this->appRoot = $basePath;

        parent::__construct($id, $basePath);
    }
    #endregion

    #region Identity
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdAsUnderscore(): array|string
    {
        return Str::toSnake($this->id);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): string
    {
        return static::VERSION;
    }
    #endregion

    #region Filesystem
    /**
     * Set the base path for the application.
     *
     * @param string $basePath Base directory path
     * @return void
     */
    protected function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * {@inheritdoc}
     */
    public function getAppRoot(): string
    {
        return $this->appRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function getAppFile(): string
    {
        if (
            function_exists('wp_get_theme')
            && class_exists('WP_Theme')
            && wp_get_theme($this->id)->exists()
        ) {
            return "{$this->getAppRoot()}/style.css";
        }

        return "{$this->getAppRoot()}/{$this->getId()}.php";
    }
    #endregion

    #region Routes
    /**
     * {@inheritdoc}
     */
    public function addRouteFile(string $path, string $type = 'api'): void
    {
        $this->routeFiles[] = [
            'type' => $type,
            'path' => $path
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRestRouteFiles(): array
    {
        return array_values(array_map(
            fn($route) => $route['path'],
            array_filter($this->routeFiles, fn($route) => $route['type'] === 'api')
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getAdminRouteFiles(): array
    {
        return array_values(array_map(
            fn($route) => $route['path'],
            array_filter($this->routeFiles, fn($route) => $route['type'] === 'admin')
        ));
    }
    #endregion

    #region Migrations
    /**
     * {@inheritdoc}
     */
    public function addMigrationFolder(string $path): void
    {
        $this->migrationFolders[] = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getMigrationFolders(): array
    {
        return $this->migrationFolders;
    }
    #endregion

    #region Configuration
    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException If the service cannot be resolved
     */
    public function config(): ConfigRepository
    {
        return $this->resolve('config');
    }

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException If the service cannot be resolved
     */
    public function settings(): SettingsRepository
    {
        return $this->resolve('settings');
    }
    #endregion

    #region WordPress Metadata
    /**
     * {@inheritdoc}
     */
    public function getHeaderField(string $headerKey): string
    {
        return '';
    }
    #endregion

    #region Validation
    /**
     * Validate the minimum required parameters for application initialization.
     *
     * This method ensures that the essential constructor arguments are provided
     * and meet the basic requirements needed to safely bootstrap the application.
     *
     * It performs validation on:
     * - The application identifier (`$id`)
     * - The base path of the application (`$basePath`)
     *
     * If any of the required parameters are missing or empty, the method will
     * interrupt the initialization process by throwing a MissingParameterException.
     *
     * This validation step guarantees that the application cannot be instantiated
     * in an invalid or incomplete state.
     *
     * @param string $id Unique identifier of the application instance.
     *                   Must be a non-empty string.
     * @param string $basePath Absolute path to the root directory of the application.
     *                         Must be a non-empty string pointing to a valid location.
     * @return void
     * @throws MissingParameterException When either `$id` or `$basePath` is empty.
     */
    private function isValidData(string $id, string $basePath): void
    {
        if (empty($id)) {
            throw new MissingParameterException('The "id" parameter is required.');
        }

        if (empty($basePath)) {
            throw new MissingParameterException('The "basePath" parameter is required.');
        }
    }
    #endregion

    #region Helpers
    /**
     * Get application (bootstrapper) cache path.
     *
     * default './boostrap/cache/'.
     *
     * @return string Absolute path to the application bootstrap cache directory.
     */
    public function getApplicationCachePath(): string
    {
        $base = rtrim($this->getBasePath(), "/\\");

        return $base . slash(path: '/bootstrap/cache');
    }

    /**
     * Detect application environment.
     *
     * The value is resolved from the application configuration file, which
     * itself falls back to the APP_ENV environment variable.
     *
     * @return string Current application environment (e.g. "dev", "prod").
     * @throws ReflectionException If the configuration service cannot be resolved.
     */
    public function getEnvironment(): string
    {
        return $this->config()->string('app.environment', 'production');
    }

    /**
     * Detect application debug enable.
     *
     * The value is resolved from the application configuration file, which
     * itself falls back to the APP_DEBUG environment variable.
     *
     * @return bool True when application debug mode is enabled.
     * @throws ReflectionException If the configuration service cannot be resolved.
     */
    public function isDebugMode(): bool
    {
        return $this->config()->boolean('app.debug', false);
    }
    #endregion
}
