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

/**
 * Defines the contract for a dependency injection container.
 *
 * A container manages the registration and resolution of services,
 * objects, factories, and arbitrary values through unique identifiers.
 *
 * Implementations are responsible for resolving constructor and method
 * dependencies automatically, supporting aliases, singleton services,
 * and factory-based definitions.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
interface ContainerInterface
{
	#region Registration
	/**
	 * Register a class definition.
	 *
	 * The specified class will be instantiated automatically whenever the
	 * identifier is resolved.
	 *
	 * @param string $identifier Unique service identifier.
	 * @param string $className Fully-qualified class name.
	 * @return void
	 */
	public function bindClass(string $identifier, string $className): void;

	/**
	 * Register an existing instance or arbitrary value.
	 *
	 * The registered value is returned directly whenever the identifier is
	 * resolved.
	 *
	 * @param string $identifier Unique service identifier.
	 * @param mixed $instance Instance or arbitrary value to register.
	 * @return void
	 */
	public function bindInstance(string $identifier, mixed $instance): void;

	/**
	 * Register a factory definition.
	 *
	 * The factory is invoked each time the identifier is resolved unless the
	 * definition is registered as a singleton.
	 *
	 * @param string $identifier Unique service identifier.
	 * @param callable $factory Factory responsible for creating the service.
	 * @return void
	 */
	public function bindFactory(string $identifier, callable $factory): void;

	/**
	 * Register a singleton service.
	 *
	 * The service is instantiated only once. Subsequent resolutions of the
	 * identifier always return the same instance.
	 *
	 * If no definition is provided, the identifier itself is treated as the
	 * class name to resolve.
	 *
	 * The service definition may be a class name or a factory closure.
	 *
	 * @param string $identifier Unique service identifier.
	 * @param mixed $definition Optional service definition.
	 * @return void
	 */
	public function singleton(string $identifier, mixed $definition = null): void;

	/**
	 * Register an alias for an existing service identifier.
	 *
	 * Aliases are resolved transparently, allowing the same service to be
	 * referenced by multiple identifiers.
	 *
	 * @param string $identifier Existing service identifier.
	 * @param string $alias Alias to associate with the identifier.
	 * @return void
	 */
	public function alias(string $identifier, string $alias): void;
	#endregion

	#region Resolution
	/**
	 * Resolve a service from the container.
	 *
	 * If the identifier refers to a registered instance, that instance is
	 * returned directly. Otherwise, the container resolves the corresponding
	 * service definition or attempts to instantiate the identifier as a class.
	 *
	 * Additional parameters are forwarded to the underlying constructor or
	 * factory when applicable.
	 *
	 * @param string $identifier Service identifier or fully-qualified class name.
	 * @param mixed ...$parameters Optional runtime parameters.
	 * @return mixed The resolved service or value.
	 */
	public function resolve(string $identifier, mixed ...$parameters): mixed;

	/**
	 * Invoke a callable resolving its dependencies automatically.
	 *
	 * Any unresolved object dependencies are resolved from the container.
	 * Explicit runtime parameters are matched by position and take precedence
	 * over automatic dependency resolution.
	 *
	 * @param callable $callable Callable to invoke.
	 * @param mixed ...$parameters Optional runtime parameters.
	 * @return mixed The callable return value.
	 */
	public function invoke(callable $callable, mixed ...$parameters): mixed;
	#endregion
}
