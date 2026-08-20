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

/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */

declare(strict_types=1);

namespace Omega\Routing;

use Exception;
use Omega\Application\ApplicationFactory;
use Omega\Http\FormRequest;
use Omega\Http\Json\JsonResource;
use Omega\Http\Json\ResourceCollection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function add_submenu_page;
use function array_any;
use function array_column;
use function array_filter;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_values;
use function call_user_func;
use function call_user_func_array;
use function current_user_can;
use function is_array;
use function is_callable;
use function is_string;
use function is_subclass_of;
use function is_wp_error;
use function preg_match_all;
use function register_rest_route;
use function reset;
use function rest_ensure_response;
use function sprintf;
use function str_replace;
use function trim;

/**
 * Core routing engine responsible for request dispatching and execution.
 *
 * This class handles route registration, grouping context, guard resolution,
 * and request execution for both REST API and WordPress admin environments.
 *
 * It acts as the central execution layer between defined routes and their
 * corresponding controller actions, using reflection-based dependency injection.
 *
 * The Router supports:
 * - REST API routing via `register_rest_route`
 * - Admin page routing via `add_submenu_page`
 * - Route grouping with nested prefix and guard stacks
 * - Automatic dependency resolution for controller methods
 *
 * It is designed to work in conjunction with RouterBuilder and the application
 * container, forming the runtime execution layer of the routing system.
 *
 * @category  Omega
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Router
{
    #region Properties
    /** @var array<int, array<string, mixed>> Registered route definitions. */
    protected array $routes = [];

    /** @var array<int, array{prefix:string, depth:int}> Stack of route prefixes for grouped routing. */
    protected array $prefixStack = [];

    /** @var array<int, array{guards:mixed, depth:int}> Stack of authorization guards per group level. */
    protected array $guardStack = [];

    /** @var string Current routing context type: 'rest' or 'admin'. */
    protected string $routeType = 'rest';

    /** @var string|null Current admin page identifier used for submenu routing. */
    protected ?string $page = null;

    /** @var int Current nesting level for route groups. */
    protected int $groupDepth = 0;

    /** @var array Additional configuration options for admin page routing. */
    protected array $pageOptions = [];
    #endregion

    #region Lifecycle
    /**
     * Router constructor.
     *
     * Initializes the router instance and optionally links it to a parent router
     * when working with nested routing groups.
     *
     * @param RouterBuilder $routerBuilder Router builder used to create and manage routes.
     * @param Router|null   $parentRouter   Optional parent router for nested routing contexts.
     */
    public function __construct(
        protected RouterBuilder $routerBuilder,
        protected ?Router $parentRouter = null
    ) {
    }
    #endregion

    #region Route Registration
    /**
     * Add a new route to the router and register it based on the current route type.
     *
     * Supports both REST and admin routes. The URI is normalized and prefixed
     * according to the current routing group context.
     *
     * @param string|array $httpMethod HTTP method(s) for the route (GET, POST, etc.).
     * @param string       $uri        Route URI pattern.
     * @param mixed        $action     Controller action definition [Class, method].
     * @return array Registered route definition.
     * @throws Exception If route registration fails.
     */
    public function addRoute(string|array $httpMethod, string $uri, mixed $action): array
    {
        $uri = $this->parseUriParameters($uri);
        $prefix = trim($this->applyPrefix(), '/');
        $guards = $this->applyGuards();

        if ($this->routeType === 'admin') {
            $this->registerAdminRoute($action, "{$prefix}{$uri}");
        } else {
            $this->registerRestRoute($prefix, $uri, $action, $guards, $httpMethod);
        }

        return $this->routes[] = [
            'method' => $httpMethod,
            'uri'    => $uri,
            'action' => $action,
            'guards' => $guards,
        ];
    }

    /**
     * Register an admin route inside WordPress admin menu system.
     *
     * Creates a submenu page and binds the route execution logic to it.
     * Access control is determined by the first resolved guard.
     *
     * @param mixed  $action Controller action [Class, method].
     * @param string $path   Full resolved admin path for the route.
     * @return void
     * @throws Exception If route processing fails.
     */
    protected function registerAdminRoute(mixed $action, string $path): void
    {
        $firstGuard = 'manage_options';
        $currentGuards = $this->applyGuards();
        if (!empty($currentGuards)) {
            $firstGuard = $currentGuards[0];
        }

        add_submenu_page(
            null,
            $this->page,
            $this->page,
            $firstGuard,
            $this->page,
            function () use ($action, $path) {
                if (!isset($_GET['path']) || trim($_GET['path'], "/") === trim($path, "/") || $path === '*') {
                    $this->processRequest($action, []);
                } else {
                    return new WP_Error('not_found', 'Page not found', ['status' => 404]);
                }
            }
        );

        // add_action( 'load-' . $hook_suffix, function () use ($hook_suffix) {
        //  add_action( 'admin_enqueue_scripts', function ($hook) use ($hook_suffix) {
        //      if ( $hook === $hook_suffix ) {
        //          $here = 'hero';
        //      }
        //  } );
        // } );
    }

    /**
     * Register a REST API route using WordPress register_rest_route.
     *
     * Attaches a callback that resolves dependencies, executes the controller action,
     * and normalizes the response into a WP REST response or WP_Error.
     *
     * Permission checks are evaluated using guards (callables or capability strings).
     *
     * @param string       $prefix     API namespace/prefix.
     * @param string       $uri        Route URI pattern.
     * @param mixed        $action     Controller action [Class, method].
     * @param array        $guards     List of authorization rules (capabilities or callbacks).
     * @param string|array $httpMethod HTTP method or list of methods (GET, POST, etc.).
     * @return void
     */
    protected function registerRestRoute(
        string $prefix,
        string $uri,
        mixed $action,
        array $guards,
        string|array $httpMethod = 'GET'
    ): void {
        register_rest_route(
            $prefix,
            $uri,
            [
                'methods'  => $httpMethod,
                'callback' => function (WP_REST_Request $request) use ($action) {
                    try {
                        $response = $this->processRequest($action, $request);
                        if ($response instanceof ResourceCollection || $response instanceof JsonResource) {
                            return rest_ensure_response($response->toArray());
                        }
                        return rest_ensure_response($response);
                    } catch (Exception $e) {
                        return new WP_Error('server_error', 'An unexpected server error occurred.', ['status' => 500]);
                    }
                },
                'permission_callback' => function () use ($guards) {
                    foreach ($guards as $guard) {
                        if (is_callable($guard)) {
                            if (!call_user_func($guard)) {
                                return false;
                            }
                        } elseif (is_string($guard)) {
                            if (!current_user_can($guard)) {
                                return false;
                            }
                        } elseif (is_array($guard)) {
                            if (array_any($guard, fn($g) => is_string($g) && !current_user_can($g))) {
                                return false;
                            }
                        }
                    }
                    return true;
                }
            ]
        );
    }
    #endregion

    #region Request Dispatching
    /**
     * Process a controller request and dispatch it to REST or Admin handler.
     *
     * @param array $action  Controller action [class, method].
     * @param mixed $request Optional request payload or WP_REST_Request.
     * @return array|null|WP_REST_Response|WP_Error|ResourceCollection|JsonResource
     * @throws Exception If request processing fails.
     */
    protected function processRequest(array $action, mixed $request = null): array|null|WP_REST_Response|WP_Error|ResourceCollection|JsonResource
    {
        if ($this->routeType === 'admin') {
            $this->processAdminRequest($action, $request);
            return null;
        }

        return $this->processRestRequest($action, $request);
    }

    /**
     * Handle REST request execution and return API response.
     *
     * Resolves controller dependencies, executes method and normalizes output.
     *
     * @param array $action Controller class and method.
     * @param WP_REST_Request $request Incoming REST request instance.
     * @return WP_REST_Response|WP_Error|array|ResourceCollection|JsonResource Normalized API response.
     * @throws Exception If controller resolution fails.
     */
    private function processRestRequest(array $action, WP_REST_Request $request): WP_REST_Response|WP_Error|array|ResourceCollection|JsonResource
    {
        [$controllerClass, $method] = $action;

        $reflector = new ReflectionClass($controllerClass);

        $instance = $reflector->getConstructor()
            ? $reflector->newInstanceArgs($this->resolveDependencies($reflector->getConstructor()))
            : new $controllerClass();

        $calledMethod = $reflector->getMethod($method);
        $dependencies = $this->resolveDependencies($calledMethod, $request);

        if (is_wp_error($dependencies)) {
            return $dependencies;
        }

        $result = call_user_func_array([$instance, $method], $dependencies);

        return $result ?? [];
    }

    /**
     * Handle admin request execution and render output directly.
     *
     * Executes controller method and prints result as HTML or debug output.
     *
     * @param array $action Controller class and method.
     * @param mixed $request Optional request payload.
     * @return void
     * @throws Exception If controller resolution fails.
     */
    private function processAdminRequest(array $action, mixed $request = null): void
    {
        [$controllerClass, $method] = $action;

        $reflector = new ReflectionClass($controllerClass);

        $instance = $reflector->getConstructor()
            ? $reflector->newInstanceArgs($this->resolveDependencies($reflector->getConstructor()))
            : new $controllerClass();

        $calledMethod = $reflector->getMethod($method);
        $dependencies = $this->resolveDependencies($calledMethod, $request);

        if (is_wp_error($dependencies)) {
            echo '<div class="error"><p>' . esc_html($dependencies->get_error_message()) . '</p></div>';
            return;
        }

        $result = call_user_func_array([$instance, $method], $dependencies);

        if (is_string($result)) {
            echo $result;
        } elseif (is_array($result)) {
            echo '<pre>' . esc_html(print_r($result, true)) . '</pre>';
        }
    }
    #endregion

    #region Dependency Resolution
    /**
     * Resolve method dependencies using reflection and IoC container.
     *
     * Iterates over each parameter of the target method and resolves it
     * through the appropriate strategy: FormRequest validation, direct
     * request injection, container resolution, or default value fallback.
     *
     * @param ReflectionMethod           $method  Target method to resolve.
     * @param WP_REST_Request|array|null $request Current request context.
     * @return WP_Error|array Resolved dependency arguments.
     * @throws Exception If a dependency cannot be resolved.
     */
    protected function resolveDependencies(
        ReflectionMethod $method,
        WP_REST_Request|array|null $request = null
    ): WP_Error|array {
        $resolved = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if ($type === null) {
                $resolved[] = $this->resolveDefaultParameter($param, $method);
            } elseif ($type->isBuiltin()) {
                $resolved[] = $this->resolveDefaultParameter($param, $method);
            } else {
                $result = $this->resolveTypedParameter($type, $param, $request);

                if ($result instanceof WP_Error) {
                    return $result;
                }

                $resolved[] = $result;
            }
        }

        return $resolved;
    }

    /**
     * Resolve a typed (non-builtin) parameter to its corresponding value.
     *
     * Dispatches to the appropriate resolution strategy based on the
     * parameter's type: FormRequest validation, direct request injection,
     * or IoC container resolution.
     *
     * @param ReflectionNamedType        $type    Parameter type reflection.
     * @param ReflectionParameter        $param   Parameter reflection.
     * @param WP_REST_Request|array|null $request Current request context.
     * @return mixed Resolved value or validation error.
     * @throws Exception If the dependency cannot be resolved.
     */
    private function resolveTypedParameter(
        ReflectionNamedType $type,
        ReflectionParameter $param,
        WP_REST_Request|array|null $request
    ): mixed {
        $className = $type->getName();

        if (is_subclass_of($className, FormRequest::class)) {
            return $this->resolveFormRequest($className, $param, $request);
        }

        if ($className === WP_REST_Request::class) {
            return $this->resolveRestRequest($param, $request);
        }

        return $this->resolveContainerDependency($className, $param);
    }

    /**
     * Resolve a FormRequest parameter by instantiating and validating it.
     *
     * Creates the FormRequest subclass from the incoming REST request,
     * runs validation, and returns either the validated request or a
     * WP_Error describing the first validation failure.
     *
     * @param string                     $className FormRequest subclass name.
     * @param ReflectionParameter        $param     Parameter reflection.
     * @param WP_REST_Request|array|null $request   Current request context.
     * @return FormRequest|WP_Error Validated request or validation error.
     */
    private function resolveFormRequest(
        string $className,
        ReflectionParameter $param,
        WP_REST_Request|array|null $request
    ): FormRequest|WP_Error {
        if (!$request instanceof WP_REST_Request) {
            return new WP_Error(
                'invalid_request',
                "FormRequest requires a WP_REST_Request instance for parameter '{$param->getName()}'."
            );
        }

        $formRequest = new $className($request);
        $formRequest->validate();

        if ($formRequest->fails()) {
            $errors = $formRequest->errors();
            $firstError = reset($errors) ?: 'Validation error';

            return new WP_Error('validation_error', $firstError, $errors);
        }

        return $formRequest;
    }

    /**
     * Resolve a WP_REST_Request parameter by injecting the current request.
     *
     * Returns the request directly when available, otherwise throws an
     * exception indicating the request context is missing.
     *
     * @param ReflectionParameter        $param   Parameter reflection.
     * @param WP_REST_Request|array|null $request Current request context.
     * @return WP_REST_Request The resolved request instance.
     * @throws Exception If no valid request is available.
     */
    private function resolveRestRequest(
        ReflectionParameter $param,
        WP_REST_Request|array|null $request
    ): WP_REST_Request {
        if ($request instanceof WP_REST_Request) {
            return $request;
        }

        throw new Exception(
            sprintf(
                "WP_REST_Request requested but no valid request available for parameter '%s'.",
                $param->getName()
            )
        );
    }

    /**
     * Resolve a non-builtin, non-request parameter via the IoC container.
     *
     * Delegates resolution to the application container. If the container
     * cannot resolve the dependency, the original exception is wrapped with
     * a descriptive message identifying the class and parameter.
     *
     * @param string            $className Fully-qualified class name.
     * @param ReflectionParameter $param   Parameter reflection.
     * @return object The resolved dependency instance.
     * @throws Exception If the container cannot resolve the dependency.
     */
    private function resolveContainerDependency(
        string $className,
        ReflectionParameter $param
    ): object {
        try {
            return ApplicationFactory::app($className);
        } catch (Exception) {
            throw new Exception(
                sprintf(
                    "Cannot resolve dependency '%s' for parameter '%s'.",
                    $className,
                    $param->getName()
                )
            );
        }
    }

    /**
     * Resolve a parameter without a type hint using its default value.
     *
     * Returns the default value when available, otherwise throws an
     * exception identifying the unresolvable parameter.
     *
     * @param ReflectionParameter $param  Parameter reflection.
     * @param ReflectionMethod    $method Declaring method reflection.
     * @return mixed The parameter's default value.
     * @throws Exception If no default value is available.
     */
    private function resolveDefaultParameter(
        ReflectionParameter $param,
        ReflectionMethod $method
    ): mixed {
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new Exception(
            sprintf(
                "Cannot resolve parameter '%s' in method %s.",
                $param->getName(),
                $method->getName()
            )
        );
    }
    #endregion

    #region URI Handling
    /**
     * Convert URI parameters in `{param}` format into regex named capture groups.
     *
     * @param mixed $uri Route URI containing optional placeholders.
     * @return mixed Normalized URI regex pattern.
     */
    protected function parseUriParameters(mixed $uri): mixed
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $uri, $matches);

        return array_reduce($matches[1], function ($uri, $param) {
            return str_replace('{' . $param . '}', '(?P<' . $param . '>[^/]+)', $uri);
        }, $uri);
    }
    #endregion

    #region Route Groups
    /**
     * Set a route prefix scoped to the current group depth.
     *
     * @param mixed $prefix Route prefix string.
     * @return static
     */
    public function prefix(mixed $prefix): static
    {
        $this->prefixStack[$this->groupDepth] = [
            'prefix' => trim($prefix, '/'),
            'depth'  => $this->groupDepth
        ];
        return $this;
    }

    /**
     * Define a grouped routing context with shared prefix and guards.
     *
     * Routes defined inside the callback inherit current group configuration.
     *
     * @param callable $callback Route definition callback.
     * @return static
     */
    public function group(callable $callback): static
    {
        $this->routerBuilder->increaseGroupDepth();
        $this->groupDepth++;

        $this->parentRouter?->setPage($this->page);


        $callback($this);

        // Remove prefixes and guards from current depth
        $this->prefixStack = array_filter($this->prefixStack, function ($item) {
            return $item['depth'] < $this->groupDepth;
        });

        $this->guardStack = array_filter($this->guardStack, function ($item) {
            return $item['depth'] < $this->groupDepth;
        });

        $this->parentRouter?->setPage(null);

        $this->routerBuilder->decreaseGroupDepth();
        $this->groupDepth--;

        return $this;
    }

    /**
     * Assign middleware-like guards to the current route/group.
     *
     * In admin context, only the first guard is used as required capability.
     *
     * @param mixed $guards Single guard, array of guards or callable permission rules.
     * @return static
     */
    public function guards(mixed $guards): static
    {
        if ($this->routeType === 'admin') {
            if (is_array($guards)) {
                $guards = $guards[0] ?? 'manage_options';
            }
        }

        $this->guardStack[$this->groupDepth] = [
            'guards' => $guards,
            'depth'  => $this->groupDepth
        ];

        return $this;
    }
    #endregion

    #region Routing Context
    /**
     * Set the current admin page identifier and switch to admin routing mode.
     *
     * Propagates the page context to parent router if available.
     *
     * @param mixed $page Page identifier (slug or hook name).
     * @return static
     */
    public function setPage(mixed $page): static
    {
        $this->page = $page;
        $this->admin();
        $this->parentRouter?->setPage($page);

        return $this;
    }

    /**
     * Switch router mode to REST API routing.
     *
     * @return static
     */
    public function rest(): static
    {
        $this->routeType = 'rest';

        return $this;
    }

    /**
     * Switch router mode to WordPress admin routing.
     *
     * @return static
     */
    public function admin(): static
    {
        $this->routeType = 'admin';

        return $this;
    }

    /**
     * Create a new router instance bound to an admin page context.
     *
     * Useful for nested admin routing groups.
     *
     * @param mixed $id Page identifier.
     * @param array $options Optional page configuration.
     * @return Router New router instance.
     */
    public function page(mixed $id, array $options = []): Router
    {
        $instance = new self($this->routerBuilder, $this);
        $instance->setPage($id);

        return $instance;
    }
    #endregion

    #region Context Resolution
    /**
     * Build the full route prefix based on the current group stack.
     *
     * Prefixes are concatenated respecting group depth hierarchy.
     *
     * @return string Resolved route prefix.
     */
    protected function applyPrefix(): string
    {
        if (!empty($this->prefixStack)) {
            $currentPrefixes = array_filter($this->prefixStack, function ($item) {
                return $item['depth'] <= $this->groupDepth;
            });

            $prefixes = array_map(function ($item) {
                return $item['prefix'];
            }, $currentPrefixes);

            $fullPrefix = implode('/', $prefixes);

            return '/' . $fullPrefix;
        }

        return '/';
    }

    /**
     * Resolve active guards for the current route group.
     *
     * Flattens nested guard definitions and filters by group depth.
     *
     * @return array List of resolved guards.
     */
    protected function applyGuards(): array
    {
        if (empty($this->guardStack)) {
            return [];
        }

        $currentGuards = array_filter($this->guardStack, function ($item) {
            return $item['depth'] <= $this->groupDepth;
        });

        return array_merge(...array_map(
            fn($item) => is_array($item['guards']) ? $item['guards'] : [$item['guards']],
            array_values($currentGuards)
        ));
    }
    #endregion

    #region Accessor
    /**
     * Retrieve all registered routes.
     *
     * @return array List of defined routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
    #endregion
}
