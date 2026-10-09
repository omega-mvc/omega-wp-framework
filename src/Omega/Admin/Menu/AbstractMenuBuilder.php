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

use Omega\Application\ApplicationInterface;

use function add_action;
use function add_menu_page;
use function add_submenu_page;
use function remove_submenu_page;

/**
 * Abstract base class for building WordPress admin menu structures.
 *
 * This class provides a fluent API for defining admin menus and submenus
 * in a structured way, decoupled from direct WordPress rendering logic.
 *
 * Menu definitions are collected during the build phase and later registered
 * into WordPress using the `create()` method.
 *
 * Subclasses must implement the `register()` method to define their menu structure.
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
abstract class AbstractMenuBuilder
{
    /** @var array<int, Menu> Top-level menus. Each item represents a Menu instance. */
    protected array $menus = [];

    /** @var callable|null Optional group callback used for grouped menu definitions. */
    protected mixed $groupCallback = null;

    /** @var Menu|null Currently active menu instance being built. */
    protected ?Menu $currentMenu = null;

    /**
     * Application container instance.
     *
     * Provides access to services, configuration, and dependency resolution
     * required during menu construction.
     *
     * @param ApplicationInterface $app The application container instance.
     */
    public function __construct(protected ApplicationInterface $app)
    {
    }

    /**
     * Create and register a new top-level menu.
     *
     * Initializes a Menu instance, assigns slug and title, and stores it
     * for later rendering during the `create()` phase.
     *
     * @param string $slug Unique identifier used by WordPress for the menu.
     * @param string $title Display title shown in the admin sidebar.
     *
     * @return Menu The newly created menu instance for further configuration.
     */
    public function add(string $slug, string $title): Menu
    {
        $menu = new Menu()
            ->slug($slug)
            ->title($title);

        $this->menus[] = $menu;
        $this->currentMenu = $menu;

        return $menu;
    }

    /**
     * Create and register a submenu item under a specific parent menu.
     *
     * The submenu is registered through WordPress `add_submenu_page` hook
     * at the specified priority position.
     *
     * @param string $parentSlug Parent menu slug.
     * @param string $slug Unique submenu identifier.
     * @param string $title Display title for the submenu item.
     * @param int $position Hook priority determining registration order (default: 99).
     *
     * @return Submenu The newly created submenu instance.
     */
    public function addSubmenu(
        string $parentSlug,
        string $slug,
        string $title,
        int $position = 99
    ): Submenu {
        $submenu = new Submenu($parentSlug)
            ->slug($slug)
            ->title($title);

        add_action('admin_menu', function () use ($submenu): void {
            if (!AdminPageRegistry::register($submenu->getSlug())) {
                return;
            }

            add_submenu_page(
                $submenu->getParentMenu(),
                $submenu->getTitle(),
                $submenu->getTitle(),
                $submenu->getCapability(),
                $submenu->getSlug(),
                $submenu->getCallback()
            );
        }, $position);

        return $submenu;
    }

    /**
     * Register all defined menus and submenus into WordPress.
     *
     * This method iterates through all collected menu definitions and
     * registers them using WordPress admin APIs. It also removes duplicate
     * submenu entries automatically created by WordPress for top-level menus.
     *
     * @return void
     */
    public function create(): void
    {
        foreach ($this->menus as $menu) {
            add_menu_page(
                $menu->getTitle(),
                $menu->getTitle(),
                $menu->getCapability(),
                $menu->getSlug(),
                function (): void {
                    // Rendering is handled via routing layer, not WordPress callbacks.
                },
                $menu->getIcon(),
                $menu->getPosition()
            );

            foreach ($menu->getSubmenus() as $submenu) {
                if (!AdminPageRegistry::register($submenu->getSlug())) {
                    continue;
                }

                add_submenu_page(
                    $menu->getSlug(),
                    $submenu->getTitle(),
                    $submenu->getTitle(),
                    $submenu->getCapability(),
                    $submenu->getSlug(),
                    function (): void {
                        // Rendering is handled via routing layer, not WordPress callbacks.
                    }
                );
            }

            remove_submenu_page($menu->getSlug(), $menu->getSlug());
        }
    }

    /**
     * Define the menu structure for the implementing class.
     *
     * This method must be implemented by subclasses and is responsible
     * for declaring all menus and submenus using the fluent API.
     *
     * @return void
     */
    abstract public function register(): void;
}
