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

use Omega\Application\Application;
use Omega\Application\ApplicationInterface;
use Omega\Application\Exception\MissingParameterException;
use Omega\Application\Exception\WordPressEnvironmentException;
use Omega\Config\ConfigRepository;
use Omega\Container\Container;
use Omega\Container\ContainerInterface;
use Omega\Container\Exceptions\ClassNotFoundException;
use Omega\Database\Database;
use Omega\Database\Migrations\Migrator;
use Omega\Routing\RouteLoader;
use Omega\Routing\RouterBuilder;
use Omega\Settings\SettingsRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversClassesThatImplementInterface;
use Tests\Application\Support\FakeProvider;
use Tests\Routing\Support\WPTheme;
use Tests\Routing\WordPressRuntime;

use function rtrim;

use const DIRECTORY_SEPARATOR;

/**
 * Tests the Application class behavior.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Application::class)]
#[CoversClass(MissingParameterException::class)]
#[CoversClassesThatImplementInterface(ApplicationInterface::class)]
final class ApplicationTest extends ApplicationTestCase
{
    /**
     * Test the constructor stores the id and normalizes the base path.
     */
    public function testConstructsWithIdAndNormalizedBasePath(): void
    {
        $app = new Application('sample', $this->themeBasePath() . '/');

        $this->assertSame('sample', $app->getId());
        $this->assertSame(rtrim($this->themeBasePath(), DIRECTORY_SEPARATOR), $app->getBasePath());
        $this->assertSame($app->getBasePath(), $app->getAppRoot());
    }

    /**
     * Test an empty application id is rejected.
     */
    public function testConstructorThrowsWhenIdIsEmpty(): void
    {
        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('The "id" parameter is required.');

        new Application('', $this->themeBasePath());
    }

    /**
     * Test an empty base path is rejected.
     */
    public function testConstructorThrowsWhenBasePathIsEmpty(): void
    {
        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('The "basePath" parameter is required.');

        new Application('sample', '');
    }

    /**
     * Test the id is converted to underscore format.
     */
    public function testGetIdAsUnderscoreConvertsHyphens(): void
    {
        $app = new Application('user-name', $this->themeBasePath());

        $this->assertSame('user_name', $app->getIdAsUnderscore());
    }

    /**
     * Test route files are grouped by type when retrieved.
     */
    public function testAddsAndRetrievesRouteFilesByType(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $app->addRouteFile('/routes/api.php', 'api');
        $app->addRouteFile('/routes/admin.php', 'admin');
        $app->addRouteFile('/routes/api-extra.php', 'api');

        $this->assertSame(['/routes/api.php', '/routes/api-extra.php'], $app->getRestRouteFiles());
        $this->assertSame(['/routes/admin.php'], $app->getAdminRouteFiles());
    }

    /**
     * Test migration folders are stored and returned in order.
     */
    public function testAddsAndRetrievesMigrationFolders(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $app->addMigrationFolder('/database/migrations');
        $app->addMigrationFolder('/database/seeds');

        $this->assertSame(['/database/migrations', '/database/seeds'], $app->getMigrationFolders());
    }

    /**
     * Test the config service resolves the configuration directory contents.
     */
    public function testConfigResolvesRepositoryFromConfigDirectory(): void
    {
        $app = new Application('theme', $this->themeBasePath());

        $config = $app->config();

        $this->assertInstanceOf(ConfigRepository::class, $config);
        $this->assertSame('Sample Theme', $config->string('app.name', ''));
    }

    /**
     * Test the environment is read from the application configuration.
     */
    public function testGetEnvironmentReadsConfiguredValue(): void
    {
        $app = new Application('theme', $this->themeBasePath());

        $this->assertSame('staging', $app->getEnvironment());
    }

    /**
     * Test the environment falls back to "production" when not configured.
     */
    public function testGetEnvironmentFallsBackToProductionDefault(): void
    {
        $app = new Application('sample', $this->emptyBasePath());

        $this->assertSame('production', $app->getEnvironment());
    }

    /**
     * Test the debug mode is read from the application configuration.
     */
    public function testIsDebugModeReadsConfiguredValue(): void
    {
        $app = new Application('sample', $this->pluginBasePath());

        $this->assertTrue($app->isDebugMode());
    }

    /**
     * Test the debug mode defaults to false when not configured.
     */
    public function testIsDebugModeDefaultsToFalse(): void
    {
        $app = new Application('sample', $this->emptyBasePath());

        $this->assertFalse($app->isDebugMode());
    }

    /**
     * Test the bootstrap cache path is built from the base path.
     */
    public function testGetApplicationCachePath(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $this->assertSame($this->themeBasePath() . '/bootstrap/cache', $app->getApplicationCachePath());
    }

    /**
     * Test the application file points to the theme stylesheet when a theme
     * matching the application id is installed.
     */
    public function testGetAppFileReturnsThemeStylesheetWhenThemeExists(): void
    {
        WordPressRuntime::$theme = new WPTheme([], true);
        $app = new Application('sample', $this->themeBasePath());

        $this->assertSame($this->themeBasePath() . '/style.css', $app->getAppFile());
    }

    /**
     * Test the application file points to the plugin entry file when no theme
     * matches the application id.
     */
    public function testGetAppFileReturnsPluginEntryFileWhenNoThemeMatches(): void
    {
        WordPressRuntime::$theme = new WPTheme([], false);
        $app = new Application('sample', $this->themeBasePath());

        $this->assertSame($this->themeBasePath() . '/sample.php', $app->getAppFile());
    }

    /**
     * Test the settings service resolves the settings repository.
     */
    public function testSettingsResolvesSettingsRepository(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $this->assertInstanceOf(SettingsRepository::class, $app->settings());
    }

    /**
     * Test the base application exposes no header field.
     */
    public function testGetHeaderFieldReturnsEmptyStringByDefault(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $this->assertSame('', $app->getHeaderField('Version'));
    }

    /**
     * Test a string provider is instantiated and its binding is registered.
     */
    public function testRegisterAcceptsStringProviderAndResolvesBinding(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $provider = $app->register(FakeProvider::class);

        $this->assertInstanceOf(FakeProvider::class, $provider);
        $this->assertSame(1, FakeProvider::$registerCalls);
        $this->assertSame('fake', $app->resolve('fake.service'));
    }

    /**
     * Test registering the same provider twice is idempotent.
     */
    public function testRegisterIsIdempotentForTheSameClass(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $first = $app->register(FakeProvider::class);
        $second = $app->register(FakeProvider::class);

        $this->assertSame($first, $second);
        $this->assertSame(1, FakeProvider::$registerCalls);
    }

    /**
     * Test a provider instance is returned as-is.
     */
    public function testRegisterAcceptsProviderInstance(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $provider = new FakeProvider($app);

        $this->assertSame($provider, $app->register($provider));
    }

    /**
     * Test bootstrap invokes boot() on every registered provider.
     */
    public function testBootstrapInvokesBootOnRegisteredProviders(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $app->register(FakeProvider::class);
        $app->bootstrap();

        $this->assertSame(1, FakeProvider::$bootCalls);
    }

    /**
     * Test the core container bindings resolve to the application instance.
     */
    public function testCoreContainerBindingsResolveToTheAppInstance(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $this->assertSame($app, $app->resolve(ContainerInterface::class));
        $this->assertSame($app, $app->resolve(Container::class));
        $this->assertSame($app, $app->resolve(ApplicationInterface::class));
    }

    /**
     * Test the base framework services are registered in the container.
     */
    public function testCoreServicesAreRegistered(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $this->assertInstanceOf(ConfigRepository::class, $app->resolve('config'));
        $this->assertInstanceOf(SettingsRepository::class, $app->resolve('settings'));
        $this->assertInstanceOf(RouterBuilder::class, $app->resolve('router'));
        $this->assertInstanceOf(RouteLoader::class, $app->resolve(RouteLoader::class));
        $this->assertInstanceOf(Database::class, $app->resolve('database'));
        $this->assertInstanceOf(Migrator::class, $app->resolve('migrator'));
    }

    /**
     * Test the view provider is not registered in CLI environments.
     */
    public function testCliModeDoesNotRegisterViewProvider(): void
    {
        $app = new Application('sample', $this->themeBasePath());

        $this->expectException(ClassNotFoundException::class);

        $app->resolve('view');
    }

    /**
     * Test the database service requires the WordPress runtime.
     */
    public function testDatabaseResolutionThrowsWhenWordPressIsMissing(): void
    {
        global $wpdb;

        $savedWpdb = $wpdb;
        $wpdb = null;

        try {
            $app = new Application('sample', $this->themeBasePath());

            $this->expectException(WordPressEnvironmentException::class);

            $app->resolve('database');
        } finally {
            $wpdb = $savedWpdb;
        }
    }
}
