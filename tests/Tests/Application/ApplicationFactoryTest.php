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

use Omega\Application\ApplicationFactory;
use Omega\Application\ApplicationPlugin;
use Omega\Application\ApplicationTheme;
use Omega\Application\Exceptions\FileNotFoundException;
use Omega\Config\ConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Tests\Application\Support\FakeProvider;

/**
 * Tests the ApplicationFactory class behavior.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ApplicationFactory::class)]
final class ApplicationFactoryTest extends ApplicationTestCase
{
    /**
     * Clear the shared application registry before and after each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFactory();
    }

    protected function tearDown(): void
    {
        $this->resetFactory();

        parent::tearDown();
    }

    /**
     * Test createPlugin builds and bootstraps a plugin application.
     */
    public function testCreatePluginBootstrapsApplication(): void
    {
        $app = ApplicationFactory::createPlugin('sample', $this->pluginBasePath());

        $this->assertInstanceOf(ApplicationPlugin::class, $app);
        $this->assertSame($app, ApplicationFactory::app());
    }

    /**
     * Test createTheme builds and bootstraps a theme application.
     */
    public function testCreateThemeBootstrapsApplication(): void
    {
        $app = ApplicationFactory::createTheme('theme', $this->themeBasePath());

        $this->assertInstanceOf(ApplicationTheme::class, $app);
        $this->assertSame($app, ApplicationFactory::app());
    }

    /**
     * Test createPlugin rejects a plugin without an entry file.
     */
    public function testCreatePluginThrowsWhenPluginFileIsMissing(): void
    {
        $this->expectException(FileNotFoundException::class);

        ApplicationFactory::createPlugin('sample', $this->setFixturePath('/fixtures/app/plugin'));
    }

    /**
     * Test the providers.php file is loaded and the providers are booted.
     */
    public function testCreatePluginLoadsUserProvidersFileAndBootsThem(): void
    {
        ApplicationFactory::createPlugin('sample', $this->pluginBasePath());

        $this->assertSame(1, FakeProvider::$registerCalls);
        $this->assertSame(1, FakeProvider::$bootCalls);
    }

    /**
     * Test app() returns the first registered application by default.
     */
    public function testAppReturnsFirstRegisteredApplication(): void
    {
        $plugin = ApplicationFactory::createPlugin('sample', $this->pluginBasePath());
        $theme = ApplicationFactory::createTheme('theme', $this->themeBasePath());

        $this->assertSame($plugin, ApplicationFactory::app());
        $this->assertNotSame($theme, ApplicationFactory::app());
    }

    /**
     * Test app() returns the requested application by id.
     */
    public function testAppReturnsApplicationById(): void
    {
        $plugin = ApplicationFactory::createPlugin('sample', $this->pluginBasePath());
        $theme = ApplicationFactory::createTheme('theme', $this->themeBasePath());

        $this->assertSame($theme, ApplicationFactory::app(null, 'theme'));
        $this->assertSame($plugin, ApplicationFactory::app(null, 'sample'));
    }

    /**
     * Test app() resolves a service from the first registered application.
     */
    public function testAppResolvesServiceFromFirstApplication(): void
    {
        ApplicationFactory::createPlugin('sample', $this->pluginBasePath());

        $this->assertInstanceOf(ConfigRepository::class, ApplicationFactory::app('config'));
        $this->assertSame('fake', ApplicationFactory::app('fake.service'));
    }

    /**
     * Test app() resolves a service from a specific application.
     */
    public function testAppResolvesServiceFromRequestedApplication(): void
    {
        ApplicationFactory::createPlugin('sample', $this->pluginBasePath());
        ApplicationFactory::createTheme('theme', $this->themeBasePath());

        $sample = ApplicationFactory::app('config', 'sample');
        $theme = ApplicationFactory::app('config', 'theme');

        $this->assertInstanceOf(ConfigRepository::class, $sample);
        $this->assertSame('local', $sample->string('app.environment', ''));
        $this->assertSame('staging', $theme->string('app.environment', ''));
    }

    /**
     * Reset the shared applications registry via reflection.
     */
    private function resetFactory(): void
    {
        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, []);
    }
}
