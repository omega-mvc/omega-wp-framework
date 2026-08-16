<?php

/**
 * Part of Omega - Tests Localization Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Localization;

use InvalidArgumentException;
use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use Omega\Application\ApplicationPlugin;
use Omega\Config\ConfigRepository;
use Omega\Localization\LocalizationServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Tests\Application\ApplicationTestCase;
use Tests\Application\Support\AbstractApplicationStub;
use Tests\Routing\WordPressRuntime;

/**
 * Tests the LocalizationServiceProvider behavior.
 *
 * @category  Tests
 * @package   Localization
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(LocalizationServiceProvider::class)]
final class LocalizationServiceProviderTest extends ApplicationTestCase
{
    /**
     * Reset the registries and define the WordPress plugins directory constant.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('WP_PLUGIN_DIR')) {
            define('WP_PLUGIN_DIR', dirname($this->localeBasePath()));
        }
    }

    /**
     * Test the provider registers the init hook on boot.
     */
    public function testBootRegistersInitHook(): void
    {
        $app      = ApplicationFactory::createPlugin('locale', $this->localeBasePath());
        $provider = new LocalizationServiceProvider($app);

        $provider->boot();

        $this->assertSame(
            ['init', [$provider, 'init']],
            WordPressRuntime::$actions[array_key_last(WordPressRuntime::$actions)]
        );
    }

    /**
     * Test the plugin text domain is loaded with a path relative to WP_PLUGIN_DIR.
     */
    public function testInitLoadsPluginTextDomainWithRelativePath(): void
    {
        $app      = ApplicationFactory::createPlugin('locale', $this->localeBasePath());
        $provider = new LocalizationServiceProvider($app);

        $provider->init();

        $this->assertSame(
            [['plugin', 'locale', false, 'locale/resources/languages']],
            WordPressRuntime::$textdomains
        );
    }

    /**
     * Test the theme text domain is loaded with an absolute language path.
     */
    public function testInitLoadsThemeTextDomainWithAbsolutePath(): void
    {
        $app = ApplicationFactory::createPlugin('locale', $this->localeBasePath());
        $this->bindTranslationConfig($app, 'theme', true);
        $provider = new LocalizationServiceProvider($app);

        $provider->init();

        $this->assertSame(
            [['theme', 'locale', $this->localeBasePath() . '/resources/languages']],
            WordPressRuntime::$textdomains
        );
    }

    /**
     * Test no text domain is loaded when translations are disabled.
     */
    public function testInitDoesNothingWhenTranslationDisabled(): void
    {
        $app = ApplicationFactory::createPlugin('locale', $this->localeBasePath());
        $this->bindTranslationConfig($app, 'plugin', false);
        $provider = new LocalizationServiceProvider($app);

        $provider->init();

        $this->assertSame([], WordPressRuntime::$textdomains);
    }

    /**
     * Test no text domain is loaded when translation configuration is missing.
     */
    public function testInitDoesNothingWhenTranslationConfigMissing(): void
    {
        $app      = ApplicationFactory::createPlugin('sample', $this->pluginBasePath());
        $provider = new LocalizationServiceProvider($app);

        $provider->init();

        $this->assertSame([], WordPressRuntime::$textdomains);
    }

    /**
     * Test an invalid translation type raises an exception.
     */
    public function testInitThrowsOnInvalidTranslationType(): void
    {
        $app = ApplicationFactory::createPlugin('locale', $this->localeBasePath());
        $this->bindTranslationConfig($app, 'invalid', true);
        $provider = new LocalizationServiceProvider($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid translation type "invalid" configured.');

        $provider->init();
    }

    /**
     * Test the provider is registered as a base provider outside CLI mode.
     */
    public function testRegisteredAsBaseProviderOutsideCliMode(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath(), false);

        $this->assertArrayHasKey(LocalizationServiceProvider::class, $this->serviceProvidersOf($app));
    }

    /**
     * Test the provider is not registered as a base provider in CLI mode.
     */
    public function testNotRegisteredAsBaseProviderInCliMode(): void
    {
        $app = new AbstractApplicationStub('sample', $this->themeBasePath());

        $this->assertArrayNotHasKey(LocalizationServiceProvider::class, $this->serviceProvidersOf($app));
    }

    /**
     * Replace the application config with the given translation settings.
     *
     * @param ApplicationPlugin $app    Application under test.
     * @param string            $type   Translation type.
     * @param bool              $enable Whether translations are enabled.
     */
    private function bindTranslationConfig(ApplicationPlugin $app, string $type, bool $enable): void
    {
        $app->singleton('config', fn () => new ConfigRepository([
            'app' => [
                'translation' => [
                    'type'   => $type,
                    'enable' => $enable,
                ],
            ],
        ]));
    }

    /**
     * Read the registered service providers from the application kernel.
     *
     * @param Application $app Application under test.
     * @return array<class-string, object> Registered providers keyed by class name.
     */
    private function serviceProvidersOf(Application $app): array
    {
        $reflection = new ReflectionClass($app);
        $property   = $reflection->getProperty('serviceProviders');

        return $property->getValue($app);
    }
}
