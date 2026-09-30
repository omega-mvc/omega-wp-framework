<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database;

use Omega\Application\ApplicationFactory;
use Omega\Database\Database;
use Omega\Database\DatabaseServiceProvider;
use Omega\Database\Migrations\Migrator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\WordPressRuntime;

use function array_filter;
use function count;

/**
 * Tests the SQL compatibility adjustments applied by the database provider.
 *
 * @category  Tests
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(DatabaseServiceProvider::class)]
final class DatabaseServiceProviderTest extends DatabaseTestCase
{
    /**
     * Test that placeholder markers become native SQL NULL operators.
     */
    public function testRestoreNullOperatorsConvertsPlaceholderMarkers(): void
    {
        $provider = new DatabaseServiceProvider($this->pluginApp());

        $this->assertSame(
            'SELECT * FROM wp_posts WHERE deleted_at IS NULL AND id IS NOT NULL',
            $provider->restoreNullOperators(
                "SELECT * FROM wp_posts WHERE deleted_at IS '!#####NULL#####!' AND id IS NOT '!#####NULL#####!'"
            )
        );
    }

    /**
     * Test that registration binds the database and the migrator as singletons.
     */
    public function testRegisterBindsTheDatabaseAndTheMigrator(): void
    {
        $provider = new DatabaseServiceProvider($this->pluginApp());

        $provider->register();

        $this->assertInstanceOf(Database::class, ApplicationFactory::app('database'));
        $this->assertInstanceOf(Migrator::class, ApplicationFactory::app('migrator'));
    }

    /**
     * Test that booting registers the query filter restoring NULL operators.
     */
    public function testBootRegistersTheQueryFilter(): void
    {
        $provider = new DatabaseServiceProvider($this->pluginApp());

        $provider->boot();

        $registered = array_filter(
            WordPressRuntime::$filters,
            static function (array $args) use ($provider): bool {
                $callback = $args[1] ?? null;

                return ($args[0] ?? null) === 'query'
                    && is_array($callback)
                    && ($callback[0] ?? null) === $provider;
            }
        );

        $this->assertCount(1, $registered);
    }
}
