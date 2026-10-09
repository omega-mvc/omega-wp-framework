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
use function remove_action;

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

    /** @var array<int, callable> Notice callbacks registered through this manager. */
    private array $noticeCallbacks = [];

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

        return array_any($this->hiddenPages, fn(string $page): bool => $current_page === $page);
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
     * Register a notice callback managed by this instance.
     *
     * The callback is attached to both the user and network admin notice
     * hooks and tracked so that it can later be removed without affecting
     * notices registered by other plugins.
     *
     * @param callable $callback Notice rendering callback.
     * @return void
     */
    public function addNotice(callable $callback): void
    {
        $this->noticeCallbacks[] = $callback;

        add_action('user_admin_notices', $callback);
        add_action('admin_notices', $callback);
    }

    /**
     * Remove the notices managed by this instance for configured pages.
     *
     * Only callbacks registered through {@see self::addNotice()} are removed,
     * so notices added by other plugins remain untouched.
     *
     * @return void
     */
    public function hideNotices(): void
    {
        if (!$this->maybeHideNotices()) {
            return;
        }

        foreach ($this->noticeCallbacks as $callback) {
            remove_action('user_admin_notices', $callback);
            remove_action('admin_notices', $callback);
        }
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
