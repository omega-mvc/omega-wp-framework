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

/**
 * Tests the database service provider bindings.
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
     * Test that registration binds the database and the migrator as singletons.
     */
    public function testRegisterBindsTheDatabaseAndTheMigrator(): void
    {
        $provider = new DatabaseServiceProvider($this->pluginApp());

        $provider->register();

        $this->assertInstanceOf(Database::class, ApplicationFactory::app('database'));
        $this->assertInstanceOf(Migrator::class, ApplicationFactory::app('migrator'));
    }
}
