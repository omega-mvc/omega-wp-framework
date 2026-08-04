<?php

/**
 * Part of Omega - Tests Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\CoversNothing;

use function ltrim;
use function Omega\Application\slash;

/**
 * Trait FixturesPathTrait
 *
 * Provides helper methods to access test fixture files and define
 * a consistent base path for initializing Application instances
 * during testing.
 *
 * This trait assumes it is located in the root of the tests folder
 * alongside the `fixtures` directory. It simplifies paths by
 * removing the need to manually calculate directory levels.
 *
 * Methods:
 * - fixturePath(string $path = ''): Returns the full path to a fixture file.
 * - basePath(): Returns the root path for initializing Application instances.
 *
 * @category  Tests
 * @package   Tests
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
#[CoversNothing]
trait FixturesPathTrait
{
    /**
     * Returns the full path to a fixture file.
     *
     * @param string $path Relative path from the fixtures root directory
     * @return string Full path to the fixture file
     */
    protected function setFixturePath(string $path = ''): string
    {
        return slash(__DIR__ . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    /**
     * Returns the base path for initializing Application instances in tests.
     *
     * Points to the root of the tests folder where fixtures are located.
     *
     * @return string Full path to the test root directory
     */
    protected function setFixtureBasePath(): string
    {
        return __DIR__;
    }
}
