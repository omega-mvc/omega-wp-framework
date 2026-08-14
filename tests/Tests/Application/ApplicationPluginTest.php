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

use Omega\Application\ApplicationPlugin;
use Omega\Application\Exception\FileNotFoundException;
use Omega\Application\Exception\HeaderNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\WordPressRuntime;

/**
 * Tests the ApplicationPlugin class behavior.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ApplicationPlugin::class)]
#[CoversClass(FileNotFoundException::class)]
#[CoversClass(HeaderNotFoundException::class)]
final class ApplicationPluginTest extends ApplicationTestCase
{
    /**
     * Test the plugin application is created with a valid plugin structure.
     */
    public function testConstructsWithValidPluginStructure(): void
    {
        $app = new ApplicationPlugin('sample', $this->pluginBasePath());

        $this->assertSame('sample', $app->getId());
        $this->assertSame($this->pluginBasePath(), $app->getBasePath());
    }

    /**
     * Test a missing plugin entry file is rejected.
     */
    public function testConstructorThrowsWhenPluginFileIsMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('sample');

        new ApplicationPlugin('sample', $this->setFixturePath('/fixtures/app/plugin'));
    }

    /**
     * Test the plugin exposes its framework name.
     */
    public function testNameReturnsPluginName(): void
    {
        $app = new ApplicationPlugin('sample', $this->pluginBasePath());

        $this->assertSame('Omega Plugin', $app->getName());
    }

    /**
     * Test the plugin exposes its framework version.
     */
    public function testVersionReturnsPluginVersion(): void
    {
        $app = new ApplicationPlugin('sample', $this->pluginBasePath());

        $this->assertSame('1.0.0', $app->getVersion());
    }

    /**
     * Test a plugin header value is returned by get_file_data().
     */
    public function testGetHeaderFieldReturnsValueFromPluginFile(): void
    {
        WordPressRuntime::$fileHeaders = ['Version' => '1.2.3'];

        $app = new ApplicationPlugin('sample', $this->pluginBasePath());

        $this->assertSame('1.2.3', $app->getHeaderField('Version'));
    }

    /**
     * Test an empty plugin header raises an exception.
     */
    public function testGetHeaderFieldThrowsWhenHeaderValueIsEmpty(): void
    {
        WordPressRuntime::$fileHeaders = ['Version' => ''];

        $app = new ApplicationPlugin('sample', $this->pluginBasePath());

        $this->expectException(HeaderNotFoundException::class);

        $app->getHeaderField('Version');
    }
}
