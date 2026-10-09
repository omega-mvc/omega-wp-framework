<?php

/**
 * Part of Omega - Admin Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Admin\Menu;

use Closure;
use TypeError;

/**
 * Represents a submenu item within the WordPress admin menu system.
 *
 * A Submenu is a child element of a top-level Menu and is responsible for
 * defining navigation entries that are nested under a parent menu item.
 *
 * In addition to standard menu metadata inherited from AbstractMenuItem,
 * this class supports:
 * - Parent menu association
 * - Dynamic callback execution
 * - Optional routing path integration for internal framework routing
 *
 * Submenus act as bridge elements between WordPress admin UI and the
 * internal routing/view system of the framework.
 *
 * @category   Omega
 * @package    Admin
 * @subpackage Menu
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class Submenu extends AbstractMenuItem
{
    /**
     * Callback responsible for rendering or handling the submenu page.
     *
     * Typically used by WordPress when the submenu is accessed.
     * Can be a callable pointing to a controller method or closure.
     * Invokable callables are wrapped into a Closure on assignment.
     *
     * @var Closure|null
     */
    public ?Closure $callback = null;

    /**
     * Parent menu instance or identifier.
     *
     * Represents the menu to which this submenu belongs.
     * Can be a Menu instance or another resolvable identifier.
     *
     * @var mixed
     */
    protected mixed $parentMenu = null;

    /**
     * Submenu constructor.
     *
     * Initializes the submenu with an optional parent menu reference.
     *
     * @param mixed $parentMenu Parent menu instance or identifier.
     */
    public function __construct(mixed $parentMenu = null)
    {
        $this->parentMenu = $parentMenu;
    }

    /**
     * Retrieve the parent menu reference.
     *
     * @return mixed Parent menu instance or identifier.
     */
    public function getParentMenu(): mixed
    {
        return $this->parentMenu;
    }

    /**
     * Assign or update the parent menu reference.
     *
     * @param mixed $parentMenu Parent menu instance or identifier.
     * @return static
     */
    public function setParentMenu(mixed $parentMenu): static
    {
        $this->parentMenu = $parentMenu;

        return $this;
    }

    /**
     * Define the callback used to render or handle this submenu.
     *
     * The callback is executed when WordPress loads the submenu page.
     *
     * @param callable $callback Function or method responsible for rendering the page.
     * @return static
     */
    public function setCallback(callable $callback): static
    {
        $this->callback = $callback instanceof Closure
            ? $callback
            : static function (mixed ...$arguments) use ($callback): mixed {
                return $callback(...$arguments);
            };

        return $this;
    }

    /**
     * Retrieve the submenu callback handler.
     *
     * @return callable Function or method used for rendering or processing the submenu.
     */
    public function getCallback(): callable
    {
        if ($this->callback === null) {
            throw new TypeError('Cannot read the callback of a submenu that has no callback assigned.');
        }

        return $this->callback;
    }

    /**
     * Retrieve the effective submenu slug.
     *
     * If a routing path is defined, it is appended to the slug as a query
     * parameter to support internal routing resolution.
     *
     * @return string Fully resolved submenu slug used by WordPress.
     */
    public function getSlug(): string
    {
        if ($this->getPath()) {
            return $this->slug . '&path=' . $this->getPath();
        }

        return $this->slug;
    }
}
