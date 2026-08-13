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

use Closure;
use Omega\Container\Exceptions\ClassNotFoundException;
use Omega\Container\Exceptions\DependencyResolutionException;
use Omega\Container\Exceptions\NotInstantiableException;
use Omega\Container\Exceptions\RecursiveDependencyException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;

use function array_key_exists;
use function array_map;
use function array_pop;
use function count;
use function in_array;
use function is_null;

/**
 * Dependency injection container implementation.
 *
 * The container manages the registration and resolution of services,
 * class definitions, factories, and arbitrary values through unique
 * identifiers.
 *
 * Services may be resolved automatically using constructor dependency
 * injection, created through factory callbacks, or registered as
 * singleton instances. The container also supports identifier aliases
 * and automatic dependency resolution when invoking callables.
 *
 * Circular dependencies are detected during the resolution process and
 * reported through dedicated container exceptions.
 *
 * @category  Omega
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Container implements ContainerInterface
{
    #region Properties
    /**
     * Registered service definitions.
     *
     * Each definition is represented by a factory callable responsible
     * for creating the corresponding service.
     *
     * @var array<string, callable>
     */
    private array $bindings = [];

    /**
     * Registered singleton instances and arbitrary values.
     *
     * Values stored here are returned directly without invoking
     * factories or creating new instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Registered identifier aliases.
     *
     * Each alias maps to its canonical service identifier.
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Stack of identifiers currently being resolved.
     *
     * Used to detect circular dependencies during recursive service
     * resolution.
     *
     * @var string[]
     */
    private array $dependencyStack = [];
    #endregion

    #region Registration
    /**
     * {@inheritdoc}
     */
    public function bindClass(string $identifier, string $className): void
    {
        $this->bindings[$identifier] = fn($_, ...$parameters) => $this->resolve($className, ...$parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function bindInstance(string $identifier, mixed $instance): void
    {
        $this->instances[$identifier] = $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function bindFactory(string $identifier, callable $factory): void
    {
        $this->bindings[$identifier] = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $identifier, mixed $definition = null): void
    {
        $definition = $definition ?: $identifier;

        $this->bindFactory($identifier, function ($container) use ($definition) {
            static $instance;
            if ($instance === null) {
                $instance = ($definition instanceof Closure)
                    ? $definition($container)
                    : $this->resolve($definition);
            }
            return $instance;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function alias(string $identifier, string $alias): void
    {
        while (array_key_exists($identifier, $this->aliases)) {
            $identifier = $this->aliases[$identifier];
        }

        $this->aliases[$alias] = $identifier;
    }
    #endregion

    #region Resolution
    /**
     * {@inheritdoc}
     *
     * @throws RecursiveDependencyException If a circular dependency is detected.
     * @throws ReflectionException If the service cannot be reflected.
     */
    public function resolve(string $identifier, mixed ...$parameters): mixed
    {
        $resolvedIdentifier = $this->resolveIdentifier($identifier);

        if (in_array($resolvedIdentifier, $this->dependencyStack, true)) {
            throw new RecursiveDependencyException($resolvedIdentifier);
        }

        $this->dependencyStack[] = $resolvedIdentifier;

        if (array_key_exists($resolvedIdentifier, $this->instances)) {
            array_pop($this->dependencyStack);
            return $this->instances[$resolvedIdentifier];
        }

        $instance = array_key_exists($resolvedIdentifier, $this->bindings)
            ? $this->bindings[$resolvedIdentifier]($this, ...$parameters)
            : $this->createInstance($resolvedIdentifier, ...$parameters);

        array_pop($this->dependencyStack);

        return $instance;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException If the callable cannot be reflected.
     */
    public function invoke(callable $callable, mixed ...$parameters): mixed
    {
        /** @noinspection PhpClosureCanBeConvertedToFirstClassCallableInspection */
        $reflection = new ReflectionFunction(Closure::fromCallable($callable));

        if ($reflection->getNumberOfParameters() === 0) {
            return $reflection->invoke();
        }

        return $reflection->invokeArgs($this->resolveMethodDependencies($reflection, $parameters));
    }
    #endregion

    #region Identifier Resolution
    /**
     * Resolve the canonical identifier for a service.
     *
     * If the given identifier is an alias, the corresponding canonical
     * identifier is returned. Otherwise, the original identifier is returned
     * unchanged.
     *
     * Alias chains are flattened when aliases are registered, ensuring that
     * each alias always resolves directly to its canonical identifier.
     *
     * @param string $identifier Service identifier or alias.
     * @return string The canonical service identifier.
     */
    private function resolveIdentifier(string $identifier): string
    {
        return $this->aliases[$identifier] ?? $identifier;
    }
    #endregion

    #region Instance Creation
    /**
     * Instantiate the given class resolving its constructor dependencies.
     *
     * If the class defines a constructor, all unresolved object dependencies
     * are resolved automatically from the container. Any runtime parameters
     * provided are forwarded to the constructor by position.
     *
     * @param string $className Fully-qualified class name.
     * @param mixed ...$parameters Optional runtime constructor parameters.
     * @return string|object|null A newly created class instance.
     * @throws ClassNotFoundException If the class cannot be loaded.
     * @throws NotInstantiableException If the class cannot be instantiated.
     * @throws ReflectionException If reflection fails.
     */
    private function createInstance(string $className, mixed ...$parameters): string|null|object
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException) {
            throw new ClassNotFoundException($className);
        }

        if (!$reflection->isInstantiable()) {
            throw new NotInstantiableException($className);
        }

        $constructor = $reflection->getConstructor();

        if (is_null($constructor)) {
            return $reflection->newInstance();
        }

        return $reflection->newInstanceArgs($this->resolveMethodDependencies($constructor, $parameters));
    }
    #endregion

    #region Dependency Resolution
    /**
     * Resolve the arguments required by a constructor or callable.
     *
     * Explicit runtime parameters are matched by their position. Any remaining
     * object dependencies are resolved automatically from the container,
     * while optional parameters fall back to their declared default values.
     *
     * @param ReflectionFunctionAbstract $method Reflected constructor or callable.
     * @param array<int, mixed> $parameters Explicit runtime parameters.
     * @return array<int, mixed> The complete list of resolved arguments.
     * @throws ReflectionException If dependency resolution requires reflection that cannot be completed.
     */
    private function resolveMethodDependencies(ReflectionFunctionAbstract $method, array $parameters): array
    {
        if ($method->getNumberOfParameters() === count($parameters)) {
            return $parameters;
        }

        return array_map(
            fn($parameter) => array_key_exists($parameter->getPosition(), $parameters)
                ? $parameters[$parameter->getPosition()]
                : $this->resolveMethodParameter($parameter),
            $method->getParameters()
        );
    }

    /**
     * Resolve a single reflected parameter.
     *
     * Optional parameters use their declared default value. Required object
     * dependencies are resolved automatically from the container.
     *
     * Required parameters without a class type declaration, or parameters
     * using a built-in type, cannot be resolved automatically and result
     * in a dependency resolution exception.
     *
     * @param ReflectionParameter $parameter Parameter to resolve.
     * @return mixed The resolved parameter value.
     *
     * @throws DependencyResolutionException If the parameter cannot be
     *                                      resolved automatically.
     * @throws ReflectionException If reflection fails during resolution.
     */
    private function resolveMethodParameter(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isOptional()) {
            return $parameter->getDefaultValue();
        }

        $type = $parameter->getType();

        if (is_null($type) || ($type instanceof ReflectionNamedType && $type->isBuiltin())) {
            throw new DependencyResolutionException($parameter);
        }

        return $this->resolve($type instanceof ReflectionNamedType ? $type->getName() : (string) $type);
    }
    #endregion
}
