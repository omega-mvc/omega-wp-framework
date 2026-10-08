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
use InvalidArgumentException;
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
use function class_exists;
use function count;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_null;
use function trait_exists;

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
        unset($this->instances[$identifier]);

        $this->bindings[$identifier] = fn(
            ContainerInterface $_,
            mixed ...$parameters
        ): mixed => $this->resolve($className, ...$parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function bindInstance(string $identifier, mixed $instance): void
    {
        // An explicit instance replaces any previously registered binding:
        // the most recent registration always wins.
        unset($this->bindings[$identifier]);

        $this->instances[$identifier] = $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function bindFactory(string $identifier, callable $factory): void
    {
        unset($this->instances[$identifier]);

        $this->bindings[$identifier] = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $identifier, string|Closure|null $definition = null): void
    {
        $this->bindFactory($identifier, function (ContainerInterface $container) use ($identifier, $definition): mixed {
            static $resolved = false;
            static $instance = null;

            if (!$resolved) {
                $instance = ($definition instanceof Closure)
                    ? $definition($container)
                    : $this->resolve($definition ?? $identifier);
                $resolved = true;
            }

            return $instance;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function alias(string $identifier, string $alias): void
    {
        $canonical = $identifier;
        $guard     = count($this->aliases) + 1;

        while (array_key_exists($canonical, $this->aliases)) {
            $canonical = $this->aliases[$canonical];

            if (--$guard <= 0) {
                throw new InvalidArgumentException(sprintf(
                    'Circular alias chain detected while resolving "%s".',
                    $identifier
                ));
            }
        }

        if ($canonical === $alias) {
            throw new InvalidArgumentException(sprintf(
                'Cannot alias "%s" to "%s": circular alias.',
                $alias,
                $identifier
            ));
        }

        $this->aliases[$alias] = $canonical;
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

        try {
            if (array_key_exists($resolvedIdentifier, $this->instances)) {
                return $this->instances[$resolvedIdentifier];
            }

            return array_key_exists($resolvedIdentifier, $this->bindings)
                ? $this->bindings[$resolvedIdentifier]($this, ...$parameters)
                : $this->createInstance($resolvedIdentifier, ...$parameters);
        } finally {
            array_pop($this->dependencyStack);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException If the callable cannot be reflected.
     */
    public function invoke(callable $callable, mixed ...$parameters): mixed
    {
        $reflection = new ReflectionFunction(Closure::fromCallable($callable));

        if ($reflection->getNumberOfParameters() === 0) {
            return $reflection->invoke();
        }

        return $reflection->invokeArgs($this->resolveMethodDependencies($reflection, array_values($parameters)));
    }
    #endregion

    #region Inspection
    /**
     * {@inheritdoc}
     */
    public function has(string $identifier): bool
    {
        $resolvedIdentifier = $this->resolveIdentifier($identifier);

        return array_key_exists($resolvedIdentifier, $this->instances)
            || array_key_exists($resolvedIdentifier, $this->bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $identifier): void
    {
        unset(
            $this->instances[$identifier],
            $this->bindings[$identifier],
            $this->aliases[$identifier]
        );
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
     * Check whether the given name refers to an existing class-like element.
     *
     * Returns true when the identifier matches an existing class, interface,
     * trait, or enum.
     *
     * @phpstan-assert-if-true class-string $className
     *
     * @param string $className Fully-qualified class-like name.
     * @return bool True when the name exists.
     */
    private function classLikeExists(string $className): bool
    {
        if (enum_exists($className)) {
            return true;
        } elseif (class_exists($className)) {
            return true;
        } elseif (interface_exists($className)) {
            return true;
        } elseif (trait_exists($className)) {
            return true;
        }

        return false;
    }

    /**
     * Instantiate the given class resolving its constructor dependencies.
     *
     * If the class defines a constructor, all unresolved object dependencies
     * are resolved automatically from the container. Any runtime parameters
     * provided are forwarded to the constructor by position.
     *
     * @param string $className Fully-qualified class name.
     * @param mixed ...$parameters Optional runtime constructor parameters.
     * @return object A newly created class instance.
     * @throws ClassNotFoundException If the class cannot be loaded.
     * @throws NotInstantiableException If the class cannot be instantiated.
     * @throws ReflectionException If reflection fails.
     */
    private function createInstance(string $className, mixed ...$parameters): object
    {
        if (!$this->classLikeExists($className)) {
            throw new ClassNotFoundException($className);
        }

        $reflection = new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new NotInstantiableException($className);
        }

        $constructor = $reflection->getConstructor();

        if (is_null($constructor)) {
            return $reflection->newInstance();
        }

        return $reflection->newInstanceArgs($this->resolveMethodDependencies($constructor, array_values($parameters)));
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
            fn(ReflectionParameter $parameter): mixed => array_key_exists($parameter->getPosition(), $parameters)
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

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                throw new DependencyResolutionException($parameter);
            }

            return $this->resolve($type->getName());
        }

        if (is_null($type)) {
            throw new DependencyResolutionException($parameter);
        }

        return $this->resolve((string) $type);
    }
    #endregion
}
