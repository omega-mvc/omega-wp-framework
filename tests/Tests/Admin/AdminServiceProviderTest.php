<?php

/**
 * Part of Omega - Tests Admin Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Admin;

use Omega\Admin\AdminManager;
use Omega\Admin\AdminServiceProvider;
use Omega\Admin\Features\WooCommerce;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Admin\Fixtures\EmptyAdminMenu;
use Tests\Admin\Fixtures\SampleAdminMenu;
use Tests\Admin\Fixtures\SampleAdminSetup;
use Tests\Routing\WordPressRuntime;

use function array_filter;
use function array_key_last;
use function array_values;

/**
 * Test the AdminServiceProvider registration and boot flow.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AdminServiceProvider::class)]
final class AdminServiceProviderTest extends AdminTestCase
{
    /**
     * Reset the fixture recorders before every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        SampleAdminSetup::reset();
        SampleAdminMenu::reset();
    }

    /**
     * Return the callback registered last for the given hook.
     *
     * The plugin fixture boot already registers admin hooks for the router,
     * so the last registration is the one performed under test.
     *
     * @param string $hookName Hook name to look up
     * @return mixed The recorded hook callback
     */
    private function actionCallback(string $hookName): mixed
    {
        $recorded = array_values(array_filter(
            WordPressRuntime::$actions,
            static fn(array $action): bool => $action[0] === $hookName
        ));

        if ($recorded === []) {
            $this->fail(sprintf('No "%s" hook has been registered.', $hookName));
        }

        return $recorded[array_key_last($recorded)][1] ?? null;
    }

    /**
     * Test the admin manager is bound as a singleton.
     *
     * @return void
     */
    public function testItBindsTheAdminManagerAsSingleton(): void
    {
        $manager = $this->app->resolve('admin.manager');

        $this->assertInstanceOf(AdminManager::class, $manager);
        $this->assertSame($manager, $this->app->resolve('admin.manager'));
    }

    /**
     * Test every admin feature is bound as a singleton.
     *
     * @param class-string $feature Feature class registered by the provider
     * @return void
     */
    #[DataProvider('featureProvider')]
    public function testItBindsEveryFeatureAsSingleton(string $feature): void
    {
        $resolved = $this->app->resolve($feature);

        $this->assertInstanceOf($feature, $resolved);
        $this->assertSame($resolved, $this->app->resolve($feature));
    }

    /**
     * Admin features registered by the provider.
     *
     * @return array<string, array{0: class-string}>
     */
    public static function featureProvider(): array
    {
        return [
            'woocommerce' => [WooCommerce::class],
        ];
    }

    /**
     * Test boot() hooks the menu and setup resolvers.
     *
     * @return void
     */
    public function testBootHooksTheMenuAndSetupResolvers(): void
    {
        $this->provider->boot();

        $this->assertSame([$this->provider, 'adminMenu'], $this->actionCallback('admin_menu'));
        $this->assertSame([$this->provider, 'adminSetup'], $this->actionCallback('admin_init'));
    }

    /**
     * Test boot() initializes the admin manager.
     *
     * @return void
     */
    public function testBootInitializesTheAdminManager(): void
    {
        $this->provider->boot();

        $manager = $this->app->resolve('admin.manager');
        $this->assertSame(99, has_action('in_admin_header', [$manager, 'hideNotices']));
    }

    /**
     * Test boot() registers the feature hook with the resolved feature.
     *
     * @return void
     */
    public function testBootHooksTheResolvedFeatureInstance(): void
    {
        $this->bindConfig(['features' => ['wc' => ['compatibility' => true]]]);

        $this->provider->boot();

        $this->assertSame(
            [$this->app->resolve(WooCommerce::class), 'registerFeatures'],
            $this->actionCallback('before_woocommerce_init')
        );
    }

    /**
     * Test the admin setup class configured in the config is instantiated.
     *
     * @return void
     */
    public function testAdminSetupInstantiatesTheConfiguredClass(): void
    {
        $this->bindConfig(['app' => ['admin' => ['setup' => SampleAdminSetup::class]]]);

        $this->provider->adminSetup();

        $this->assertSame(1, SampleAdminSetup::$instances);
    }

    /**
     * Test the admin setup is skipped when no class is configured.
     *
     * @return void
     */
    public function testAdminSetupSkipsAMissingClass(): void
    {
        $this->bindConfig(['app' => ['admin' => ['setup' => 'Omega\\Admin\\MissingSetup']]]);

        $this->provider->adminSetup();

        $this->assertSame(0, SampleAdminSetup::$instances);
    }

    /**
     * Test the admin setup is skipped when the config key is missing.
     *
     * @return void
     */
    public function testAdminSetupSkipsAMissingConfigKey(): void
    {
        $this->bindConfig([]);

        $this->provider->adminSetup();

        $this->assertSame(0, SampleAdminSetup::$instances);
    }

    /**
     * Test the configured admin menu is registered into WordPress.
     *
     * @return void
     */
    public function testAdminMenuRegistersTheConfiguredMenu(): void
    {
        $this->bindConfig(['app' => ['admin' => ['menu' => SampleAdminMenu::class]]]);

        $this->provider->adminMenu();

        $this->assertCount(1, WordPressRuntime::$menus);
        $this->assertSame('omega-tasks', WordPressRuntime::$menus[0][3]);
        $this->assertCount(2, WordPressRuntime::$submenus);
    }

    /**
     * Test the admin menu is skipped when the configured class is missing.
     *
     * @return void
     */
    public function testAdminMenuSkipsAMissingClass(): void
    {
        $this->bindConfig(['app' => ['admin' => ['menu' => 'Omega\\Admin\\MissingMenu']]]);

        $this->provider->adminMenu();

        $this->assertSame([], WordPressRuntime::$menus);
        $this->assertSame([], WordPressRuntime::$submenus);
    }

    /**
     * Test the admin menu is skipped when the config key is missing.
     *
     * @return void
     */
    public function testAdminMenuSkipsAMissingConfigKey(): void
    {
        $this->bindConfig([]);

        $this->provider->adminMenu();

        $this->assertSame([], WordPressRuntime::$menus);
    }

    /**
     * Test the configured admin menu without menus registers nothing.
     *
     * @return void
     */
    public function testAdminMenuSupportsAMenuBuilderWithoutMenus(): void
    {
        $this->bindConfig(['app' => ['admin' => ['menu' => EmptyAdminMenu::class]]]);

        $this->provider->adminMenu();

        $this->assertSame([], WordPressRuntime::$menus);
    }

    /**
     * Test the configured admin menu is built with the application instance.
     *
     * @return void
     */
    public function testAdminMenuBuildsTheMenuWithTheApplication(): void
    {
        $this->bindConfig(['app' => ['admin' => ['menu' => SampleAdminMenu::class]]]);

        $this->provider->adminMenu();

        $registered = array_values(array_filter(
            WordPressRuntime::$menus,
            static fn(array $menu): bool => $menu[3] === 'omega-tasks'
        ));

        $this->assertCount(1, $registered);
        $this->assertSame($this->app->getBasePath(), SampleAdminMenu::$registeredBasePaths[0] ?? null);
    }
}
