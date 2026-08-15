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
use Omega\Application\Exceptions\FileNotFoundException;
use Omega\Application\Exceptions\HeaderNotFoundException;
use Omega\Application\Exceptions\WordPressEnvironmentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\WordPressRuntime;
use InvalidArgumentException;
use RuntimeException;

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
#[CoversClass(WordPressEnvironmentException::class)]
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

    /**
     * Test the FileNotFoundException is autoloadable under the
     * Omega\Application\Exceptions namespace and correctly typed.
     */
    public function testFileNotFoundExceptionIsAutoloadableAndTyped(): void
    {
        $this->assertTrue(class_exists(FileNotFoundException::class));
        $this->assertInstanceOf(InvalidArgumentException::class, new FileNotFoundException('sample'));
        $this->assertSame('sample', (new FileNotFoundException('sample'))->getMessage());
        $this->assertSame(
            'The file "sample.php" was not found.',
            (new FileNotFoundException('The file "%s" was not found.', 'sample.php'))->getMessage()
        );
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
        $this->assertSame(
            'Plugin header "Version" not found.',
            (new HeaderNotFoundException('Plugin header "%s" not found.', 'Version'))->getMessage()
        );
    }

    /**
     * Test the WordPressEnvironmentException is autoloadable under the
     * Omega\Application\Exceptions namespace and correctly typed.
     */
    public function testWordPressEnvironmentExceptionIsAutoloadableAndTyped(): void
    {
        $this->assertTrue(class_exists(WordPressEnvironmentException::class));
        $this->assertInstanceOf(RuntimeException::class, new WordPressEnvironmentException('sample'));
        $this->assertSame('sample', (new WordPressEnvironmentException('sample'))->getMessage());
        $this->assertSame(
            'WordPress environment "test" is not available.',
            (new WordPressEnvironmentException('WordPress environment "%s" is not available.', 'test'))->getMessage()
        );
    }
}
