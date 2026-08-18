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

declare(strict_types=1);

namespace Omega\Routing;

use Exception;
use Omega\Application\ApplicationInterface;
use ReflectionException;

use function end;

/**
 * RouterBuilder
 *
 * Stateful routing builder responsible for constructing and grouping application routes.
 *
 * This class acts as a high-level interface over the underlying Router instances,
 * managing route registration, grouping depth, and contextual routing state.
 *
 * It supports route grouping through an internal stack mechanism and delegates
 * actual route registration to Router instances.
 *
 * Additionally, it integrates with the application container to trigger related
 * side effects such as admin menu registration when defining specific routes.
 *
 * The builder is designed to be used during the application boot phase,
 * where route files are dynamically loaded and executed.
 *
 * @category  Omega
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class RouterBuilder
{
    #region Properties
    /** @var array<int, Router> Stack of active Router instances created during route building. */
    protected array $instances = [];

    /** @var int Current nesting level of route groups. Used to manage grouped routing context. */
    protected int $groupDepth = 0;
    #endregion

    #region Lifecycle
    /**
     * RouterBuilder constructor.
     *
     * Initializes the builder with the application container instance.
     *
     * @param ApplicationInterface $app Application container used for service resolution.
     */
    public function __construct(protected ApplicationInterface $app)
    {
    }
    #endregion

    #region Router Instance Management
    /**
     * Retrieve the current Router instance or create a new one.
     *
     * If the builder is inside a route group, the last active Router instance is reused.
     * Otherwise, a new Router instance is created and stored in the stack.
     *
     * @return Router Active router instance used for route registration.
     */
    protected function getInstance(): Router
    {
        if ($this->groupDepth > 0) 
        {
            if (!empty($this->instances)) {
                return end($this->instances);
            }
            $instance = new Router($this);
            $this->instances[] = $instance;
            return $instance;
        } else {
            $instance = new Router($this);
            $this->instances[] = $instance;
            return $instance;
        }
    }
    #endregion

    #region Route Configuration
    /**
     * Define a URI prefix for the current route group or router context.
     *
     * @param string $prefix Route prefix applied to subsequent routes.
     * @return Router The underlying router instance.
     */
    public function prefix(string $prefix): Router
    {
        $instance = $this->getInstance();
        $instance->prefix($prefix);

        return $instance;
    }

    /**
     * Register a WordPress admin page route.
     *
     * Also registers the page in the admin manager to ensure it appears
     * in the WordPress admin interface when required.
     *
     * @param string $id Page identifier.
     * @param array $options Optional configuration for the admin page.
     * @return Router The underlying router instance.
     * @throws ReflectionException
     */
    public function page(string $id, array $options = []): Router
    {
        $instance = $this->getInstance();

        $this->app->resolve('admin.manager')->addHiddenNoticesPage($id);

        $instance->page($id, $options);

        return $instance;
    }
    #endregion

    #region HTTP Route Registration
    /**
     * Register a GET route.
     *
     * @param string $uri Route URI pattern.
     * @param mixed $action Route handler or controller action.
     * @return array Registered route definition.
     * @throws Exception
     */
    public function get(string $uri, mixed $action = null): array
    {
        $instance = $this->getInstance();

        return $instance->addRoute('GET', $uri, $action);
    }

    /**
     * Register a POST route.
     *
     * @param string $uri Route URI pattern.
     * @param mixed $action Route handler or controller action.
     * @return array Registered route definition.
     * @throws Exception
     */
    public function post(string $uri, mixed $action = null): array
    {
        $instance = $this->getInstance();

        return $instance->addRoute('POST', $uri, $action);
    }

    /**
     * Register a PUT route.
     *
     * @param string $uri Route URI pattern.
     * @param mixed $action Route handler or controller action.
     * @return array Registered route definition.
     * @throws Exception
     */
    public function put(string $uri, mixed $action = null): array
    {
        $instance = $this->getInstance();

        return $instance->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a PATCH route.
     *
     * @param string $uri Route URI pattern.
     * @param mixed $action Route handler or controller action.
     * @return array Registered route definition.
     * @throws Exception
     */
    public function patch(string $uri, mixed $action = null): array
    {
        $instance = $this->getInstance();

        return $instance->addRoute('PATCH', $uri, $action);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $uri Route URI pattern.
     * @param mixed $action Route handler or controller action.
     * @return array Registered route definition.
     * @throws Exception
     */
    public function delete(string $uri, mixed $action = null): array
    {
        $instance = $this->getInstance();

        return $instance->addRoute('DELETE', $uri, $action);
    }
    #endregion

    #region Group Context Management
    /**
     * Increase the routing group nesting level.
     *
     * Used internally when entering a grouped routing context.
     *
     * @return void
     */
    public function increaseGroupDepth(): void
    {
        $this->groupDepth++;
    }

    /**
     * Decrease the routing group nesting level.
     *
     * Used internally when exiting a grouped routing context.
     *
     * @return void
     */
    public function decreaseGroupDepth(): void
    {
        $this->groupDepth--;
    }
    #endregion
}
