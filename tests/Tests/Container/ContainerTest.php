<?php

declare(strict_types=1);

namespace Tests\Container;

use Omega\Container\Container;
use Omega\Container\ContainerInterface;
use Omega\Container\Exceptions\ClassNotFoundException;
use Omega\Container\Exceptions\DependencyResolutionException;
use Omega\Container\Exceptions\NotInstantiableException;
use Omega\Container\Exceptions\RecursiveDependencyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversClassesThatImplementInterface;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Container\Support\A;
use Tests\Container\Support\AInterface;
use Tests\Container\Support\ARecursive;
use Tests\Container\Support\B;
use Tests\Container\Support\C;
use Tests\Container\Support\D;
use Tests\Container\Support\E;
use Tests\Container\Support\F;

use function class_implements;
use function get_class;

#[CoversClass(ClassNotFoundException::class)]
#[CoversClass(Container::class)]
#[CoversClass(DependencyResolutionException::class)]
#[CoversClass(NotInstantiableException::class)]
#[CoversClass(RecursiveDependencyException::class)]
#[CoversClassesThatImplementInterface(ContainerInterface::class)]
class ContainerTest extends TestCase
{
	private Container $container;

	/**
	 * @before
	 */
	protected function setUp(): void
	{
		$this->container = new Container();
	}

	/**
	 * Should implement container interface.
	 *
	 * @return void
	 */
	public function testShouldImplementContainerInterface(): void
	{
		$this->assertContains(ContainerInterface::class, class_implements( get_class($this->container)));
	}

	/**
	 * Should resolve bound instance.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveBoundInstance(): void
	{
		$value = 'value';
		$this->container->bindInstance('identifier', $value);

		$this->assertSame($value, $this->container->resolve('identifier'));
	}

	/**
	 * Should resolve bound factory value.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveBoundFactoryValue(): void
	{
		$value = 'value';
		$this->container->bindFactory('identifier', fn() => $value);

		$this->assertSame($value, $this->container->resolve('identifier'));
	}

	/**
	 * Should resolve bound class.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveBoundClass(): void
	{
		$this->container->bindClass(AInterface::class, A::class);

		$this->assertInstanceOf(A::class, $this->container->resolve(AInterface::class));
	}

	/**
	 * Should throw exception if bound class cannot be found.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldThrowExceptionIfBoundClassCannotBeFound(): void
	{
		$this->expectException(ClassNotFoundException::class);

		$this->container->bindClass(AInterface::class, 'Non_Existing_Class');

		$this->container->resolve(AInterface::class);
	}

	/**
	 * Should resolve class instance.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveClassInstance(): void
	{
		$this->assertInstanceOf(A::class, $this->container->resolve(A::class));
	}

	/**
	 * Should resolve alias.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveAlias(): void
	{
		$value = 'value';
		$this->container->bindInstance('identifier', $value);
		$this->container->alias('identifier', 'alias');

		$this->assertSame($value, $this->container->resolve('alias'));
	}

	/**
	 * Should resolve nested alias.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveNestedAlias(): void
	{
		$value = 'value';
		$this->container->bindInstance('identifier', $value);
		$this->container->alias('identifier', 'alias');
		$this->container->alias('alias', 'another-alias');

		$this->assertSame($value, $this->container->resolve('another-alias'));
	}

	/**
	 * Should accept constructor dependencies.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldAcceptConstructorDependencies(): void
	{
		$b = $this->container->resolve(B::class, new A());

		$this->assertInstanceOf(B::class, $b);
		$this->assertInstanceOf(A::class, $b->a);
	}

	/**
	 * Should resolve constructor dependencies.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveConstructorDependencies(): void
	{
		$this->assertInstanceOf(A::class, $this->container->resolve(B::class)->a);
	}

	/**
	 * Should resolve recursive dependencies.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveRecursiveDependencies(): void
	{
		$this->assertInstanceOf(A::class, $this->container->resolve(C::class)->b->a);
	}

	/**
	 * Should use provided dependencies.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldUseProvidedDependencies(): void
	{
		$message = 'message';
		$d = $this->container->resolve(D::class, $message);

		$this->assertSame($message, $d->message);
		$this->assertInstanceOf(A::class, $d->a);
	}

	/**
	 * Should use default value if available.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldUseDefaultValueIfAvailable(): void
	{
		$message = 'message';
		$e = $this->container->resolve(E::class, $message);

		$this->assertSame($message, $e->message);
		$this->assertNull($e->a);
	}

	/**
	 * Should throw exception if a dependency cannot be resolved.
	 * 
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldThrowExceptionIfADependencyCannotBeResolved(): void
	{
		$this->expectException(DependencyResolutionException::class);

		$this->container->resolve(F::class);
	}

	/**
	 * Should throw exception if dependency is recursive.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldThrowExceptionIfDependencyIsRecursive(): void
	{
		$this->expectException(RecursiveDependencyException::class);

		$this->container->resolve(ARecursive::class);
	}

	/**
	 * Should throw exception if not instantiable.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldThrowExceptionIfNonInstantiable(): void
	{
		$this->expectException(NotInstantiableException::class);

		$this->container->resolve(AInterface::class);
	}

	/**
	 * Should resolve callable.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveCallable(): void
	{
		$value = 'value';

		$this->assertSame($value, $this->container->invoke(fn() => $value));
	}

	/**
	 * Should resolve invocation dependencies.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldResolveInvocationDependencies(): void
	{
		$this->assertInstanceof(A::class, $this->container->invoke(fn(B $b) => $b->a));
	}

	/**
	 * Should throw exception if invocation dependency cannot be resolved.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldThrowExceptionIfInvocationDependencyCannotBeResolved(): void
	{
		$this->expectException(DependencyResolutionException::class);

		$this->container->invoke(fn(string $message) => $message);
	}

	/**
	 * Should throw exception if invocation parameter has no type.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldThrowExceptionIfInvocationParameterHasNoType(): void
	{
		$this->expectException(DependencyResolutionException::class);

		$this->container->invoke(fn($message) => $message);
	}

	/**
	 * Should throw exception if invocation parameter has a union type.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldThrowExceptionIfInvocationParameterHasUnionType(): void
	{
		$this->expectException(ClassNotFoundException::class);
		$this->expectExceptionMessage(A::class . '|' . B::class);

		$this->container->invoke(fn(A|B $message) => $message);
	}

	/**
	 * Should format exception message without class prefix for global functions.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testShouldFormatExceptionMessageForGlobalFunction(): void
	{
		$this->expectException(DependencyResolutionException::class);
		$this->expectExceptionMessage('while invoking strlen.');

		$this->container->invoke('strlen');
	}
}
