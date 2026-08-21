<?php

/**
 * Part of Omega - Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Database\Facade;

use Omega\Database\Database;
use Omega\Database\Migrations\Migrator;
use Omega\Database\ORM\QueryBuilder;
use Omega\Facade\AbstractFacade;

/**
 * Provides static access to the database layer.
 *
 * This facade exposes the primary database services provided by the
 * framework, including:
 *
 * - query builder access
 * - migration management
 * - direct database interaction
 *
 * The underlying service is resolved from the application container
 * using the "database" binding.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Facade
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL V3.0+
 * @version    1.0.0
 *
 * @method static Migrator migrator()
 * @method static QueryBuilder table(string $table)
 * @method static string prepare(string $query, mixed ...$args)
 * @method static bool|int query(string $query)
 * @method static array<int|string, mixed>|object|null getResults(string $query)
 * @method static string|null getVar(string $query)
 * @method static bool|int delete(string $table, array<int|string, mixed> $whereValues, ?array<int|string, mixed> $whereFormat = null)
 * @method static bool|int insert(string $table, array<int|string, mixed> $data)
 * @method static bool|int update(string $table, array<int|string, mixed> $data, array<int|string, mixed> $whereValues)
 * @method static bool|int insertMultiple(string $tableName, array<int|string, mixed> $data)
 * @method static bool tableExists(string $tableName)
 * @method static string getTableName(string $tableName, string $prefix = '')
 * @method static void createOrUpdateTable(string $tableName, array<int|string, mixed> $columns)
 *
 * @see Database
 */
class DB extends AbstractFacade
{
    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor(): string
    {
        return 'database';
    }
}
