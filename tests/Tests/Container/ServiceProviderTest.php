<?php

/**
 * Part of Omega - Tests Container Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Container;

use Omega\Application\Application;
use Omega\Container\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Application\ApplicationTestCase;

/**
 * Tests the base ServiceProvider resource helpers.
 *
 * Verifies that loadRoutesFrom() and loadMigrationsFrom() delegate to the
 * corresponding application registrars.
 *
 * @category  Tests
 * @package   Container
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ServiceProvider::class)]
final class ServiceProviderTest extends ApplicationTestCase
{
    /**
     * Test loadRoutesFrom registers route files with the given type.
     *
     * @return void
     */
    public function testLoadRoutesFromRegistersRouteFilesByType(): void
    {
        $app      = new Application('theme', $this->themeBasePath());
        $provider = new ServiceProvider($app);

        $provider->loadRoutesFrom('/routes/api-extra.php', 'api');
        $provider->loadRoutesFrom('/routes/admin-extra.php', 'admin');

        $this->assertSame(['/routes/api-extra.php'], $app->getRestRouteFiles());
        $this->assertSame(['/routes/admin-extra.php'], $app->getAdminRouteFiles());
    }

    /**
     * Test loadRoutesFrom defaults to the api route type.
     *
     * @return void
     */
    public function testLoadRoutesFromDefaultsToApiType(): void
    {
        $app = new Application('theme', $this->themeBasePath());

        (new ServiceProvider($app))->loadRoutesFrom('/routes/custom.php');

        $this->assertSame(['/routes/custom.php'], $app->getRestRouteFiles());
        $this->assertSame([], $app->getAdminRouteFiles());
    }

    /**
     * Test loadMigrationsFrom registers the migration folder.
     *
     * @return void
     */
    public function testLoadMigrationsFromRegistersMigrationFolder(): void
    {
        $app = new Application('theme', $this->themeBasePath());

        (new ServiceProvider($app))->loadMigrationsFrom('/database/migrations/custom');

        $this->assertSame(['/database/migrations/custom'], $app->getMigrationFolders());
    }
}
