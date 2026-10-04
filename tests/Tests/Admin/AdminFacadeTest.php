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
use Omega\Admin\Facade\AdminManager as AdminManagerFacade;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test the Admin facade proxy.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AdminManagerFacade::class)]
final class AdminFacadeTest extends AdminTestCase
{
    /**
     * Test the facade exposes the admin manager accessor.
     *
     * @return void
     */
    public function testItReturnsTheFacadeAccessor(): void
    {
        $this->assertSame('admin.manager', AdminManagerFacade::getFacadeAccessor());
    }

    /**
     * Test the facade root is the admin manager of the container.
     *
     * @return void
     */
    public function testItResolvesTheAdminManagerAsFacadeRoot(): void
    {
        $this->assertSame($this->app->resolve('admin.manager'), AdminManagerFacade::getFacadeRoot());
    }

    /**
     * Test the static call is proxied to the container service.
     *
     * @return void
     */
    public function testItProxiesTheStaticCallsToTheContainerService(): void
    {
        $manager = $this->app->resolve('admin.manager');
        $this->assertInstanceOf(AdminManager::class, $manager);

        AdminManagerFacade::addHiddenNoticesPage('omega-hidden');
        $_GET['page'] = 'omega-hidden';

        $this->assertTrue($manager->maybeHideNotices());
    }

    /**
     * Test the facade caches the resolved manager for the accessor.
     *
     * @return void
     */
    public function testItCachesTheResolvedManager(): void
    {
        $root = AdminManagerFacade::getFacadeRoot();

        $this->assertSame($root, AdminManagerFacade::getFacadeRoot());
    }

    /**
     * Test clearing the cached instance resolves the service again.
     *
     * @return void
     */
    public function testClearingTheCacheResolvesTheServiceAgain(): void
    {
        $root = AdminManagerFacade::getFacadeRoot();

        AdminManagerFacade::clearResolvedInstance('admin.manager');

        $this->assertSame($this->app->resolve('admin.manager'), AdminManagerFacade::getFacadeRoot());
        $this->assertSame($root, $this->app->resolve('admin.manager'));
    }
}
