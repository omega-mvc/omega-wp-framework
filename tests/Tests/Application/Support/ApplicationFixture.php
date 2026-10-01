<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Application\Support;

use function dirname;
use function Omega\Application\slash;

use const DIRECTORY_SEPARATOR;

/**
 * Static helpers exposing the application fixture paths.
 *
 * Pest binds the body of an `it()` closure to the generated test case with a
 * scope that is unrelated to the bound base test case, so protected helpers of
 * ApplicationTestCase cannot be called from the closure itself. Fixture paths
 * therefore live here as public static methods, mirroring the
 * Tests\Console\Fixtures\ConsoleSupport pattern already used in this suite.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class ApplicationFixture
{
    /**
     * Base directory holding the shared application fixtures.
     *
     * Resolved with dirname() instead of a literal '../../' prefix: a parent
     * segment left in the returned path would never match a real file path,
     * because ApplicationFactory::matchingAppId() compares an application root
     * against the `file` of the backtrace frames, and those are always canonical.
     *
     * @return string Absolute fixtures path with a trailing separator
     */
    private static function fixturesBasePath(): string
    {
        return slash(path: dirname(__DIR__, 2) . '/fixtures') . DIRECTORY_SEPARATOR;
    }

    /**
     * Base path of the plugin fixture with an entry file and a providers file.
     *
     * @return string Absolute fixture path
     */
    public static function pluginBasePath(): string
    {
        return self::fixturesBasePath() . 'app/plugin/sample';
    }

    /**
     * Base path of the theme fixture.
     *
     * @return string Absolute fixture path
     */
    public static function themeBasePath(): string
    {
        return self::fixturesBasePath() . 'app/theme';
    }

    /**
     * Base path of the fixture without any configuration file.
     *
     * @return string Absolute fixture path
     */
    public static function emptyBasePath(): string
    {
        return self::fixturesBasePath() . 'app';
    }

    /**
     * Base path of the plugin fixture used to resolve an application by backtrace.
     *
     * @return string Absolute fixture path
     */
    public static function resolverBasePath(): string
    {
        return self::fixturesBasePath() . 'app/plugin/resolver';
    }

    /**
     * Base path of the plugin fixture shipping locale files.
     *
     * @return string Absolute fixture path
     */
    public static function localeBasePath(): string
    {
        return self::fixturesBasePath() . 'app/plugin/locale';
    }

    /**
     * Base path of the fixture whose providers file does not return an array.
     *
     * @return string Absolute fixture path
     */
    public static function nonArrayProvidersBasePath(): string
    {
        return self::fixturesBasePath() . 'app/providers-nonarray';
    }

    /**
     * Base path of the plugin fixture root without an entry file.
     *
     * @return string Absolute fixture path
     */
    public static function missingPluginBasePath(): string
    {
        return self::fixturesBasePath() . 'app/plugin';
    }

    /**
     * Base path of the fake WordPress root loaded to bootstrap the plugin parser.
     *
     * @return string Absolute fixture path with a trailing separator
     */
    public static function wordpressBasePath(): string
    {
        return self::fixturesBasePath() . 'app/plugin/wp' . DIRECTORY_SEPARATOR;
    }
}
