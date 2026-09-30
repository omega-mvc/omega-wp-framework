<?php

/**
 * Part of Omega - Tests\Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database;

use Omega\Database\Database;
use Omega\Database\Facade\DB;
use Omega\Facade\AbstractFacade;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the database facade.
 *
 * @category  Tests
 * @package   Database
 * @subpackage Facade
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(DB::class)]
final class DatabaseFacadeTest extends DatabaseTestCase
{
    /**
     * Drop the statically cached facade roots between tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        AbstractFacade::clearResolvedInstances();
    }

    /**
     * Test the facade points to the database binding.
     */
    public function testItResolvesTheDatabaseAccessor(): void
    {
        $this->assertSame('database', DB::getFacadeAccessor());
    }

    /**
     * Test the facade root is the bound database service.
     */
    public function testItResolvesTheDatabaseServiceAsFacadeRoot(): void
    {
        $root = DB::getFacadeRoot();

        $this->assertInstanceOf(Database::class, $root);
        $this->assertSame($root, DB::getFacadeRoot());
    }

    /**
     * Test the facade forwards static calls to the database service.
     */
    public function testItForwardsCallsToTheDatabaseService(): void
    {
        $this->assertSame('wp_books', DB::getTableName('books'));
    }
}
