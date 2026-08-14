<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Routing\Support;

/**
 * Lightweight stand-in for the WordPress WP_Theme class.
 *
 * Exposes the `get()` and `exists()` methods consumed by the Application
 * package so it can run inside a plain PHPUnit process.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class WPTheme
{
    /**
     * @param array<string, string> $headers Theme header values.
     */
    public function __construct(private array $headers = [])
    {
    }

    /**
     * Retrieve a theme header value.
     *
     * @param string $key Header key.
     * @return string The header value, or an empty string when missing.
     */
    public function get(string $key): string
    {
        return $this->headers[$key] ?? '';
    }

    /**
     * Determine whether the theme exists.
     *
     * @return bool Always true for stubbed themes.
     */
    public function exists(): bool
    {
        return true;
    }
}
