<?php

/**
 * Part of Omega - Tests\Routing Package.
 *
 * Minimal stand-in for the WordPress upgrade routines. The real file pulls in
 * the whole MySQL schema diff engine; the framework only requires it to be
 * loaded before calling dbDelta(), which is provided by the test doubles in
 * tests/Tests/Routing/WordPressFunctions.php.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);
