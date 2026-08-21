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

namespace Omega\Admin;

use function add_action;
use function array_any;
use function remove_all_actions;

/**
 * Manage WordPress admin panel behavior and runtime UI adjustments.
 *
 * Handles admin notice visibility and tracks pages that require
 * a simplified or isolated rendering environment.
 *
 * @category  Omega
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class AdminManager
{
    /** @var array<int|string, string> List of admin page identifiers where notices should be hidden. */
    private array $hiddenPages = [];

    /**
     * Create a new admin manager instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Initialize admin manager hooks.
     *
     * Registers internal WordPress admin hooks used by the manager.
     *
     * @return void
     */
    public function init(): void
    {
        add_action('in_admin_header', [$this, 'hideNotices'], 99);
    }

    /**
     * Determine whether admin notices should be hidden for the current page.
     *
     * @return bool True when notices should be suppressed for the current admin page
     */
    public function maybeHideNotices(): bool
    {
        if (!isset($_GET['page'])) {
            return false;
        }

        $current_page = $_GET['page'];

        return array_any($this->hiddenPages, fn($page) => $current_page === $page);
    }

    /**
     * Register an admin page where notices should be hidden.
     *
     * @param string $id Admin page identifier
     * @return void
     */
    public function addHiddenNoticesPage(string $id): void
    {
        $this->hiddenPages[] = $id;
    }

    /**
     * Remove WordPress admin notices for configured pages.
     *
     * @return void
     */
    public function hideNotices(): void
    {
        if (!$this->maybeHideNotices()) {
            return;
        }

        remove_all_actions('user_admin_notices');
        remove_all_actions('admin_notices');
    }

    /**
     * Silence default admin rendering output.
     *
     * Reserved for future rendering suppression behavior.
     *
     * @return void
     */
    public function silenceRender(): void
    {
    }
}
