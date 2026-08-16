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

use Omega\Str\Str;
use ReflectionException;

use function array_find_key;
use function array_filter;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_values;
use function class_exists;
use function count;
use function debug_backtrace;
use function file_exists;
use function file_get_contents;
use function json_decode;
use function str_contains;

/**
 * Factory and registry for Omega application instances.
 *
 * This class is the primary entry point for creating and bootstrapping
 * WordPress plugin and theme applications. It is responsible for
 * constructing application instances, executing their bootstrap process,
 * and storing them in a shared registry for later retrieval.
 *
 * When multiple applications coexist within the same WordPress runtime,
 * the factory also resolves the correct application context for service
 * lookups by inspecting the current execution stack or, when possible,
 * the requested service namespace.
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class ApplicationFactory
{
    #region Properties
    /** @var array<string, ApplicationPlugin|ApplicationTheme> Omega Application Container. */
    private static array $apps = [];
    #endregion

    #region Factory
    /**
     * Create and initialize a new Plugin application instance.
     *
     * This method is responsible for constructing an ApplicationPlugin instance,
     * registering it in the internal applications registry, and executing its
     * full bootstrap process.
     *
     * The plugin application represents a WordPress plugin environment and
     * expects the provided base path to contain a valid plugin structure,
     * including the main plugin entry file.
     *
     * After creation, the application is immediately bootstrapped, meaning
     * all service providers, bindings, and core framework components are
     * registered and made available.
     *
     * @param string $id Unique identifier of the plugin application.
     *                   This is typically the plugin directory name and must
     *                   match the plugin entry file name.
     *
     * @param string $basePath Absolute path to the root directory of the plugin.
     *
     * @return ApplicationPlugin Fully initialized and bootstrapped plugin application instance.
     */
    public static function createPlugin(string $id, string $basePath): ApplicationPlugin
    {
        self::$apps[$id] = new ApplicationPlugin(id: $id, basePath: $basePath);

        self::$apps[$id]->bootstrap();

        return self::$apps[$id];
    }

    /**
     * Create and initialize a new Theme application instance.
     *
     * This method constructs an ApplicationTheme instance, registers it in the
     * internal applications registry, and triggers its bootstrap process.
     *
     * The theme application represents a WordPress theme environment and expects
     * the provided base path to contain a valid theme structure, including the
     * required style.css file used as the theme entry point.
     *
     * After instantiation, the application is immediately bootstrapped, which
     * registers all service providers, container bindings, and framework core
     * services required for runtime execution.
     *
     * @param string $id Unique identifier of the theme application.
     *                   This is typically the theme directory name and must
     *                   correspond to the WordPress theme folder structure.
     *
     * @param string $basePath Absolute path to the root directory of the theme.
     *
     * @return ApplicationTheme Fully initialized and bootstrapped theme application instance.
     */
    public static function createTheme(string $id, string $basePath): ApplicationTheme
    {
        self::$apps[$id] = new ApplicationTheme(id: $id, basePath: $basePath);

        self::$apps[$id]->bootstrap();

        return self::$apps[$id];
    }
    #endregion

    #region Resolver
    /**
     * Get an app instance or a service from a specific app.
     *
     * @param string|null $service Service name.
     * @param string|null $appId Application ID.
     * @return mixed
     * @throws ReflectionException
     */
    public static function app(?string $service = null, ?string $appId = null): mixed
    {
        if (!$appId) {
            $appId = self::resolveAppId($service);
        }

        if (!$service) {
            return self::$apps[$appId];
        }

        return self::$apps[$appId]->resolve($service);
    }

    /**
     * Resolve the application id for the current execution context.
     *
     * When the registry holds a single app the first registered app is
     * returned directly. Otherwise the backtrace scan takes precedence over
     * the service-namespace scan, and the first registered app is the final
     * fallback.
     *
     * @param string|null $service Service name.
     * @return string The resolved application id.
     */
    private static function resolveAppId(?string $service): string
    {
        if (!self::needsResolution()) {
            return (string) array_key_first(self::$apps);
        }

        return self::appIdByTrace() ?? self::appIdByNamespace($service) ?? (string) array_key_first(self::$apps);
    }

    /**
     * Whether the application registry holds more than one app and the
     * Console package is available, requiring execution-context resolution.
     *
     * @return bool True when the app id must be resolved from the context.
     */
    private static function needsResolution(): bool
    {
        return count(self::$apps) > 1 && class_exists('Omega\Console\ConsoleApplication');
    }

    /**
     * Find the application whose root directory contains the first backtrace file.
     *
     * @return string|null The matching application id, or null when no stack
     *                     frame points into an application root.
     */
    private static function appIdByTrace(): ?string
    {
        $matches = array_filter(
            array_map(
                static fn(string $file): ?string => self::matchingAppId($file),
                array_column(debug_backtrace(), 'file')
            ),
            static fn(?string $id): bool => $id !== null
        );

        return array_values($matches)[0] ?? null;
    }

    /**
     * Find the first application whose root is contained in the given file path.
     *
     * @param string $file Absolute path of a stack frame file.
     * @return string|null The matching application id, or null when no root matches.
     */
    private static function matchingAppId(string $file): ?string
    {
        return array_find_key(
            self::$apps,
            static fn(ApplicationPlugin|ApplicationTheme $app): bool =>
                str_contains($file, $app->getAppRoot())
        );
    }

    /**
     * Resolve the application id by the service namespace prefix declared in
     * each application composer.json file.
     *
     * @param string|null $service Service name.
     * @return string|null The matching application id, or null when no prefix matches.
     */
    private static function appIdByNamespace(?string $service): ?string
    {
        if ($service === null) {
            return null;
        }

        $matches = array_filter(
            array_map(
                static fn(ApplicationPlugin|ApplicationTheme $app, string $id): ?string =>
                    self::matchesNamespace($app, $service) ? $id : null,
                self::$apps,
                array_keys(self::$apps)
            ),
            static fn(?string $id): bool => $id !== null
        );

        return array_values($matches)[0] ?? null;
    }

    /**
     * Whether the service name belongs to the application PSR-4 namespace.
     *
     * @param ApplicationPlugin|ApplicationTheme $app Application instance.
     * @param string $service Service name.
     * @return bool True when the service starts with the application prefix.
     */
    private static function matchesNamespace(ApplicationPlugin|ApplicationTheme $app, string $service): bool
    {
        $prefix = self::psr4Prefix($app);

        return $prefix !== null && Str::startsWith($service, $prefix);
    }

    /**
     * Read the first PSR-4 prefix declared by the application composer.json.
     *
     * @param ApplicationPlugin|ApplicationTheme $app Application instance.
     * @return string|null The first PSR-4 prefix, or null when no composer
     *                     autoload mapping is declared.
     */
    private static function psr4Prefix(ApplicationPlugin|ApplicationTheme $app): ?string
    {
        $composerFile = $app->getAppRoot() . '/composer.json';

        if (!file_exists($composerFile)) {
            return null;
        }

        $composer = json_decode((string) file_get_contents($composerFile), true);

        return array_key_first($composer['autoload']['psr-4'] ?? []);
    }
    #endregion
}
