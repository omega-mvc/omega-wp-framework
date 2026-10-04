<?php

/**
 * Part of Omega - Facade Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Facade;

use Omega\Application\ApplicationFactory;
use Omega\Facade\Exception\FacadeObjectNotSetException;
use ReflectionException;
use RuntimeException;

/**
 * Base facade implementation providing static access to container-bound services.
 *
 * Facades act as static proxies to underlying objects resolved from the application
 * container, allowing expressive and convenient access to services without requiring
 * explicit dependency injection.
 *
 * This implementation maintains an internal cache of resolved instances to improve
 * performance and avoid repeated container lookups.
 *
 * Concrete facades must implement the getFacadeAccessor() method, which defines
 * the container binding key used to resolve the underlying instance.
 *
 * @category  Omega
 * @package   Facade
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class AbstractFacade implements FacadeInterface
{
    #region Properties
    /**
     * Cached resolved instances, indexed by the owning application id and then
     * by the facade accessor.
     *
     * The application id is part of the key because ApplicationFactory picks
     * the container from the execution context, so the very same accessor can
     * resolve against different applications within one request.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $resolvedInstance = [];
    #endregion

    #region Static Proxy
    /**
     * Handle dynamic static method calls and proxy them to the underlying instance.
     *
     * @param string $method The method name being called.
     * @param array<int, mixed> $args The arguments passed to the method.
     * @return mixed The result of the proxied method call.
     * @throws ReflectionException When the container fails to resolve the facade
     *                             service dependencies through reflection.
     * @throws RuntimeException If no facade root instance has been resolved.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if (!$instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }
    #endregion

    #region Resolution
    /**
     * Get the root object behind the facade.
     *
     * Resolves the underlying service instance using the accessor returned by
     * the concrete facade implementation.
     *
     * @return mixed The resolved instance from the container.
     * @throws ReflectionException When the underlying service cannot be resolved
     *                              because dependency metadata cannot be inspected
     *                              through reflection.
     */
    public static function getFacadeRoot(): mixed
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor(): string
    {
        throw new FacadeObjectNotSetException(
            'Facade does not define a facade accessor.'
        );
    }

    /**
     * Resolve a facade instance from the container or cache.
     *
     * The cache is keyed by the application that owns the instance, not by the
     * accessor alone: ApplicationFactory resolves the container from the
     * execution context, so two applications exposing the same accessor would
     * otherwise share the first instance resolved.
     *
     * @param string $name The container binding key.
     * @return mixed The resolved instance.
     * @throws ReflectionException When the container cannot instantiate the bound
     *                             service due to reflection failures while
     *                             inspecting constructors or dependencies.
     */
    protected static function resolveFacadeInstance(string $name): mixed
    {
        $appId = ApplicationFactory::resolveAppId($name);

        if (isset(static::$resolvedInstance[$appId][$name])) {
            return static::$resolvedInstance[$appId][$name];
        }

        return static::$resolvedInstance[$appId][$name] = ApplicationFactory::app($name, $appId);
    }
    #endregion

    #region Cache Management
    /**
     * Remove a specific resolved instance from the cache.
     *
     * The accessor is cleared for every application, since the caller names
     * the binding key and not the application holding it.
     *
     * @param string $name The container binding key.
     * @return void
     */
    public static function clearResolvedInstance(string $name): void
    {
        foreach (static::$resolvedInstance as $appId => $instances) {
            unset(static::$resolvedInstance[$appId][$name]);
        }
    }

    /**
     * Clear all resolved facade instances from the cache.
     *
     * @return void
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstance = [];
    }
    #endregion
}
