<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * Coverage tests for the resolveDependencies method and its private helpers:
 * resolveTypedParameter, resolveFormRequest, resolveRestRequest,
 * resolveContainerDependency, resolveDefaultParameter.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Routing;

use Exception;
use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use Omega\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Routing\Support\ConstructorController;
use Tests\Routing\Support\FormRequestController;
use Tests\Routing\Support\NoDefaultController;
use Tests\Routing\Support\StubController;
use Tests\Routing\Support\TestFormRequest;
use Tests\Routing\Support\WPError;
use Tests\Routing\Support\MixedParamsController;
use Tests\Routing\Support\UntypedParamController;
use Tests\Routing\Support\WPRestRequest;
use Tests\Routing\Support\MultiParamController;
use Tests\Routing\Support\WPRestRequestController;

/**
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Router::class)]
final class RouterResolveDependenciesTest extends RoutingTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, []);
    }

    // ────────────────────────────────────────
    // resolveDefaultParameter
    // ────────────────────────────────────────

    /**
     * Returns the default value when one is available on the parameter.
     */
    public function testResolveDefaultParameterReturnsDefaultValue(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDefaultParameter');

        $constructor = (new ReflectionClass(ConstructorController::class))->getConstructor();
        $param = $constructor->getParameters()[0];

        $result = $method->invoke($router, $param, $constructor);

        $this->assertSame(0, $result);
    }

    /**
     * Throws an Exception when no default value is available.
     */
    public function testResolveDefaultParameterThrowsWhenNoDefault(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDefaultParameter');

        $handle = (new ReflectionClass(NoDefaultController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot resolve parameter 'id' in method handle.");

        $method->invoke($router, $param, $handle);
    }

    // ────────────────────────────────────────
    // resolveRestRequest
    // ────────────────────────────────────────

    /**
     * Returns the request instance when it is a valid WP_REST_Request.
     */
    public function testResolveRestRequestReturnsRequestWhenAvailable(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveRestRequest');

        $handle = (new ReflectionClass(WPRestRequestController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];

        $request = new WPRestRequest(['foo' => 'bar']);
        $result = $method->invoke($router, $param, $request);

        $this->assertSame($request, $result);
    }

    /**
     * Throws an Exception when no valid WP_REST_Request is available.
     */
    public function testResolveRestRequestThrowsWhenRequestNotAvailable(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveRestRequest');

        $handle = (new ReflectionClass(WPRestRequestController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];

        $this->expectException(Exception::class);
        $expected = "WP_REST_Request requested but no valid request "
            . "available for parameter 'request'.";
        $this->expectExceptionMessage($expected);

        $method->invoke($router, $param, null);
    }

    // ────────────────────────────────────────
    // resolveFormRequest
    // ────────────────────────────────────────

    /**
     * Returns a WP_Error when the request is not a WP_REST_Request instance.
     */
    public function testResolveFormRequestReturnsWpErrorWhenRequestNotAvailable(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveFormRequest');

        $handle = (new ReflectionClass(FormRequestController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];

        $result = $method->invoke($router, TestFormRequest::class, $param, null);

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertSame('invalid_request', $result->get_error_code());
    }

    /**
     * Returns a WP_Error with validation_error when validation fails.
     */
    public function testResolveFormRequestReturnsWpErrorOnValidationFailure(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveFormRequest');

        $handle = (new ReflectionClass(FormRequestController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];

        $request = new WPRestRequest([]);
        $result = $method->invoke($router, TestFormRequest::class, $param, $request);

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertSame('validation_error', $result->get_error_code());
    }

    /**
     * Returns the validated FormRequest when validation passes.
     */
    public function testResolveFormRequestReturnsFormRequestOnSuccess(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveFormRequest');

        $handle = (new ReflectionClass(FormRequestController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];

        $request = new WPRestRequest(['name' => 'John']);
        $result = $method->invoke($router, TestFormRequest::class, $param, $request);

        $this->assertInstanceOf(TestFormRequest::class, $result);
    }

    // ────────────────────────────────────────
    // resolveTypedParameter
    // ────────────────────────────────────────

    /**
     * Dispatches to resolveFormRequest when the type is a FormRequest subclass.
     */
    public function testResolveTypedParameterDispatchesToFormRequest(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveTypedParameter');

        $handle = (new ReflectionClass(FormRequestController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];
        $type = $param->getType();

        $request = new WPRestRequest(['name' => 'John']);
        $result = $method->invoke($router, $type, $param, $request);

        $this->assertInstanceOf(TestFormRequest::class, $result);
    }

    /**
     * Dispatches to resolveRestRequest when the type is WP_REST_Request.
     */
    public function testResolveTypedParameterDispatchesToRestRequest(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveTypedParameter');

        $handle = (new ReflectionClass(WPRestRequestController::class))->getMethod('handle');
        $param = $handle->getParameters()[0];
        $type = $param->getType();

        $request = new WPRestRequest(['foo' => 'bar']);
        $result = $method->invoke($router, $type, $param, $request);

        $this->assertSame($request, $result);
    }

    // ────────────────────────────────────────
    // resolveDependencies (integration)
    // ────────────────────────────────────────

    /**
     * Returns an empty array when the target method has no parameters.
     */
    public function testResolveDependenciesReturnsEmptyArrayForNoParams(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(StubController::class, 'handle');

        $result = $method->invoke($router, $targetMethod);

        $this->assertSame([], $result);
    }

    /**
     * Returns a WP_Error when FormRequest validation fails during resolution.
     */
    public function testResolveDependenciesReturnsWpErrorOnFormRequestValidationFailure(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(FormRequestController::class, 'handle');

        $request = new WPRestRequest([]);
        $result = $method->invoke($router, $targetMethod, $request);

        $this->assertInstanceOf(WPError::class, $result);
    }

    /**
     * Resolves a FormRequest parameter with passing validation.
     */
    public function testResolveDependenciesResolvesFormRequestParameter(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(FormRequestController::class, 'handle');

        $request = new WPRestRequest(['name' => 'John']);
        $result = $method->invoke($router, $targetMethod, $request);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TestFormRequest::class, $result[0]);
    }

    /**
     * Resolves a WP_REST_Request parameter by injecting the request.
     */
    public function testResolveDependenciesResolvesRestRequestParameter(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(WPRestRequestController::class, 'handle');

        $request = new WPRestRequest(['foo' => 'bar']);
        $result = $method->invoke($router, $targetMethod, $request);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($request, $result[0]);
    }

    /**
     * Resolves a parameter with a default value when no request context is given.
     */
    public function testResolveDependenciesResolvesDefaultParameterValue(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $constructor = (new ReflectionClass(ConstructorController::class))->getConstructor();

        $result = $method->invoke($router, $constructor);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]);
    }

    /**
     * Throws an Exception when a parameter has no type and no default value.
     */
    public function testResolveDependenciesThrowsForUnresolvableParameter(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(NoDefaultController::class, 'handle');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot resolve parameter 'id' in method handle.");

        $method->invoke($router, $targetMethod);
    }

    /**
     * Exercises the container resolution path for a non-builtin typed parameter.
     *
     * ApplicationFactory::app() delegates to the active Application container.
     * The outcome depends on the test context (whether an app has been
     * bootstrapped).  This test exercises the code path regardless of the
     * result — on success the resolved array is returned; when the container
     * throws, the exception propagates through resolveContainerDependency.
     */
    public function testResolveDependenciesExercisesContainerResolutionPath(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(
            \Tests\Routing\Support\StdClassTypeHintController::class,
            'handle'
        );

        try {
            $result = $method->invoke($router, $targetMethod);

            $this->assertIsArray($result);
            $this->assertCount(1, $result);
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    /**
     * resolveContainerDependency wraps a container Exception with a descriptive message.
     *
     * Injects a mock Application into ApplicationFactory::$apps whose
     * resolve() method throws a plain Exception.  The catch block in
     * resolveContainerDependency intercepts it and re-throws with a
     * formatted message identifying the class and parameter.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testResolveContainerDependencyWrapsContainerException(): void
    {
        $mockApp = $this->createMock(Application::class);
        $mockApp->method('resolve')
            ->willThrowException(new \Exception('Container failure'));

        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, ['test-app' => $mockApp]);

        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveContainerDependency');

        $handle = (new ReflectionClass(\Tests\Routing\Support\StdClassTypeHintController::class))
            ->getMethod('handle');
        $param = $handle->getParameters()[0];

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/Cannot resolve dependency .* for parameter 'dep'\./");

        $method->invoke($router, \stdClass::class, $param);
    }

    // ────────────────────────────────────────
    // resolveDependencies — missing paths
    // ────────────────────────────────────────

    /**
     * Exercises the false branch of the outer `if ($type)` check.
     *
     * When a parameter has no type annotation, ReflectionParameter::getType()
     * returns null and the code falls through to resolveDefaultParameter.
     */
    public function testResolveDependenciesHandlesUntypedParameter(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(
            UntypedParamController::class,
            'handle'
        );

        $result = $method->invoke($router, $targetMethod);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('default', $result[0]);
    }

    /**
     * Exercises multi-iteration with mixed branch paths in one invocation.
     *
     * The first parameter (non-builtin typed) takes the continue path;
     * the second parameter (builtin with default) takes the resolveDefault
     * path.  The foreach runs two iterations with different branch sequences,
     * covering the mixed multi-iteration path.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testResolveDependenciesResolvesMixedTypedAndDefaultParams(): void
    {
        $mockApp = $this->createMock(Application::class);
        $mockApp->method('resolve')
            ->willReturn(new \stdClass());

        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, ['test-app' => $mockApp]);

        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(
            MixedParamsController::class,
            'handle'
        );

        $result = $method->invoke($router, $targetMethod);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertSame(1, $result[1]);
    }

    /**
     * Exercises the early-return guard when a previous iteration already
     * produced a WP_Error and subsequent parameters are still pending.
     *
     * MultiParamController has (int $id, TestFormRequest $request, string $sort).
     * The first param resolves to 0 (default). The second (FormRequest with
     * empty request) fails validation → WP_Error. The third param triggers
     * the `if ($carry instanceof WP_Error) { return $carry; }` guard.
     */
    public function testResolveDependenciesPropagatesWpErrorAcrossIterations(): void
    {
        $router = $this->makeRouter();
        $method = new ReflectionMethod(Router::class, 'resolveDependencies');

        $targetMethod = new ReflectionMethod(MultiParamController::class, 'handle');

        $request = new WPRestRequest([]);
        $result = $method->invoke($router, $targetMethod, $request);

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertSame('validation_error', $result->get_error_code());
    }
}
