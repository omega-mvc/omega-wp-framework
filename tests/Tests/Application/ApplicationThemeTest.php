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

namespace Tests\Application;

use Omega\Application\ApplicationTheme;
use Omega\Application\Exceptions\HeaderNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\Support\WPTheme;
use Tests\Routing\WordPressRuntime;
use RuntimeException;

/**
 * Tests the ApplicationTheme class behavior.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ApplicationTheme::class)]
#[CoversClass(HeaderNotFoundException::class)]
final class ApplicationThemeTest extends ApplicationTestCase
{
    /**
     * Test the theme application is created with a valid base path.
     */
    public function testConstructsWithValidBasePath(): void
    {
        $app = new ApplicationTheme('theme', $this->themeBasePath());

        $this->assertSame('theme', $app->getId());
        $this->assertSame($this->themeBasePath(), $app->getBasePath());
    }

    /**
     * Test the theme exposes its framework name.
     */
    public function testNameReturnsThemeName(): void
    {
        $app = new ApplicationTheme('theme', $this->themeBasePath());

        $this->assertSame('Omega Theme', $app->getName());
    }

    /**
     * Test the theme exposes its framework version.
     */
    public function testVersionReturnsThemeVersion(): void
    {
        $app = new ApplicationTheme('theme', $this->themeBasePath());

        $this->assertSame('1.0.0', $app->getVersion());
    }

    /**
     * Test a theme header value is returned by wp_get_theme().
     */
    public function testGetHeaderFieldReturnsValueFromTheme(): void
    {
        WordPressRuntime::$theme = new WPTheme(['Theme Name' => 'Omega Sample']);

        $app = new ApplicationTheme('theme', $this->themeBasePath());

        $this->assertSame('Omega Sample', $app->getHeaderField('Theme Name'));
    }

    /**
     * Test an empty theme header raises an exception.
     */
    public function testGetHeaderFieldThrowsWhenHeaderValueIsEmpty(): void
    {
        WordPressRuntime::$theme = new WPTheme(['Theme Name' => '']);

        $app = new ApplicationTheme('theme', $this->themeBasePath());

        $this->expectException(HeaderNotFoundException::class);

        $app->getHeaderField('Theme Name');
    }

    /**
     * Test the HeaderNotFoundException is autoloadable under the
     * Omega\Application\Exceptions namespace and correctly typed.
     */
    public function testHeaderNotFoundExceptionIsAutoloadableAndTyped(): void
    {
        $this->assertTrue(class_exists(HeaderNotFoundException::class));
        $this->assertInstanceOf(RuntimeException::class, new HeaderNotFoundException('sample'));
        $this->assertSame('sample', (new HeaderNotFoundException('sample'))->getMessage());
    }
}
