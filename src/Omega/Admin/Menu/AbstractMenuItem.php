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

/**
 * Base abstraction for an admin menu item.
 *
 * This class represents a single navigational element within the WordPress
 * admin interface, such as a top-level menu or submenu entry.
 *
 * It provides a fluent API for defining menu metadata including slug,
 * title, capability requirements, icon, rendering view, and optional
 * routing path information.
 *
 * Menu items are designed to be consumed by higher-level builders
 * responsible for registering them into WordPress.
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
abstract class AbstractMenuItem
{
    /** @var string Unique identifier used by WordPress for routing the menu item. */
    protected string $slug = '';

    /** @var string Display title shown in the WordPress admin interface. */
    protected string $title = '';

    /** @var string Required WordPress capability to access this menu item. */
    protected string $capability = 'manage_options';

    /** @var string Dashicon or custom icon identifier for the menu item. */
    protected string $icon = '';

    /** @var string Optional routing path associated with this menu item. */
    protected string $path = '';

    /** @var string View identifier used to render the menu content. */
    protected string $view = '';

    /** @var mixed Menu position in admin sidebar. Lower values appear higher in the menu order. */
    protected mixed $position = null;

    /** @var array<int, mixed> Scripts or assets associated with this menu item. */
    protected array $scripts = [];

    /**
     * Set the menu slug identifier.
     *
     * @param string $slug Unique menu identifier used by WordPress routing.
     * @return static
     */
    public function slug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Set the display title for the menu item.
     *
     * @param string $title Human-readable menu title.
     * @return static
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the required capability to access this menu item.
     *
     * @param string $capability WordPress capability string.
     * @return static
     */
    public function capability(string $capability): static
    {
        $this->capability = $capability;

        return $this;
    }

    /**
     * Set the menu icon.
     *
     * @param string $icon Dashicon class or icon identifier.
     * @return static
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the menu position in the admin sidebar.
     *
     * @param mixed $position Numeric or WordPress-supported position value.
     * @return static
     */
    public function position(mixed $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Set the internal routing path for this menu item.
     *
     * @param string $path Route path used by internal router system.
     * @return static
     */
    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set the view identifier for rendering this menu item.
     *
     * @param string $view View or template name associated with this menu item.
     * @return static
     */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Get the menu slug.
     *
     * @return string Menu identifier used for routing.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the menu title.
     *
     * @return string Display title of the menu item.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the required capability.
     *
     * @return string WordPress capability required to access this menu item.
     */
    public function getCapability(): string
    {
        return $this->capability;
    }

    /**
     * Get the menu icon.
     *
     * @return string Icon identifier or Dashicon class.
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get the routing path associated with this menu item.
     *
     * @return string Internal application route path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the menu position.
     *
     * @return mixed Numeric or WordPress-supported position value.
     */
    public function getPosition(): mixed
    {
        return $this->position;
    }

    /**
     * Assign scripts or assets to this menu item.
     *
     * These scripts are intended to be enqueued when the menu is rendered
     * in the admin interface.
     *
     * @param array<int, mixed> $scripts List of scripts or asset definitions.
     * @return static
     */
    public function scripts(array $scripts): static
    {
        $this->scripts = $scripts;

        return $this;
    }

    /**
     * Convert the menu item into an array representation.
     *
     * Useful for debugging, serialization, or internal processing.
     *
     * @return array<string, mixed> Structured representation of the menu item.
     */
    public function toArray(): array
    {
        return [
            'slug'       => $this->slug,
            'title'      => $this->title,
            'capability' => $this->capability,
            'icon'       => $this->icon,
            'view'       => $this->view,
        ];
    }
}
