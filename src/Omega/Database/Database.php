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

namespace Omega\Database;

use Omega\Application\ApplicationInterface;
use Omega\Application\Exceptions\WordPressEnvironmentException;
use Omega\Database\Migrations\Migrator;
use Omega\Database\ORM\QueryBuilder;
use ReflectionException;
use wpdb;

use function array_fill;
use function array_keys;
use function array_map;
use function count;
use function dbDelta;
use function implode;
use function sprintf;

/**
 * Provides a lightweight database abstraction layer on top of WordPress wpdb.
 *
 * This class centralizes database operations used by the framework, including:
 * - raw query execution
 * - query preparation
 * - schema creation and updates
 * - table existence checks
 * - bulk inserts
 * - migration access
 * - dynamic query builder creation
 *
 * It acts as the primary entry point for interacting with the database layer
 * and bridges the framework internals with the native WordPress database API.
 *
 * @category  Omega
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Database
{
    #region Properties
    /** @var wpdb WordPress database connection instance. */
    protected wpdb $wpdb;

    /** @var Migrator Database migration manager instance. */
    protected Migrator $migrator;
    #endregion

    #region Lifecycle
    /**
     * Create a new database manager instance.
     *
     * Initializes the WordPress database connection and resolves
     * the migration manager from the application container.
     *
     * @param ApplicationInterface $app The current application container instance.
     * @throws WordPressEnvironmentException Thrown when the WordPress database
     *                                       object is not available.
     * @throws ReflectionException Thrown when the migrator service cannot be resolved.
     */
    public function __construct(ApplicationInterface $app)
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            throw new WordPressEnvironmentException(
                'The WordPress database object ($wpdb) is not available.'
            );
        }

        $this->wpdb     = $wpdb;
        $this->migrator = $app->resolve('migrator');
    }
    #endregion

    #region Migration
    /**
     * Retrieve the database migrator instance.
     *
     * @return Migrator The active migration manager instance.
     */
    public function migrator(): Migrator
    {
        return $this->migrator;
    }
    #endregion

    #region Schema
    /**
     * Generate a fully qualified WordPress table name.
     *
     * Automatically prepends the WordPress table prefix and
     * optionally an additional custom prefix.
     *
     * @param string $tableName The base table name.
     * @param string $prefix Optional custom table prefix.
     * @return string The fully qualified table name.
     */
    public static function getTableName(string $tableName, string $prefix = ''): string
    {
        global $wpdb;

        return sprintf('%s%s%s', $wpdb->prefix, $prefix, $tableName);
    }

    /**
     * Create or update a database table using dbDelta().
     *
     * This method generates and synchronizes the database schema
     * based on the provided column definitions.
     *
     * The table always includes:
     * - an auto-incrementing unsigned bigint primary key named "id"
     *
     * Existing tables are automatically updated when possible.
     *
     * @param string $tableName The base table name without WordPress prefix.
     * @param array<string, string> $columns Column definitions indexed by column name.
     * @return void
     */
    public static function createOrUpdateTable(string $tableName, array $columns): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $fullTableName  = self::getTableName($tableName);

        $generatedColumns = array_map(function ($columnName, $columnType) {
            return "$columnName $columnType";
        }, array_keys($columns), $columns);

        $sql = "CREATE TABLE $fullTableName (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			" . implode(",\n", $generatedColumns) . ",
			PRIMARY KEY  (id)
		) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }

    /**
     * Determine whether a database table exists.
     *
     * @param string $tableName The full table name to check.
     * @return bool True when the table exists, otherwise false.
     */
    public static function tableExists(string $tableName): bool
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $tableName)
        );

        return $exists !== null;
    }
    #endregion

    #region Query Builder
    /**
     * Create a query builder instance for a database table.
     *
     * This method internally creates a temporary dynamic model
     * bound to the specified table.
     *
     * @param string $table The base table name without prefix.
     * @return QueryBuilder A query builder instance for the table.
     * @throws ReflectionException If the parent model fails to resolve property or schema metadata via reflection.
     */
    public static function table(string $table): QueryBuilder
    {
        $model = new DynamicModel([], self::getTableName($table));

        return $model->getQueryBuilder();
    }
    #endregion

    #region Query Execution
    /**
     * Prepare a SQL query using WordPress placeholder formatting.
     *
     * Safely escapes and formats query bindings using wpdb::prepare().
     *
     * @param string $query The SQL query containing placeholders.
     * @param mixed ...$args Query bindings used to replace placeholders.
     * @return string The prepared and escaped SQL query.
     */
    public function prepare(string $query, mixed ...$args): string
    {
        return $this->wpdb->prepare($query, $args);
    }

    /**
     * Execute a raw SQL query.
     *
     * @param string $query The SQL query to execute.
     * @return bool|int False on failure or the number of affected rows.
     */
    public function query(string $query): bool|int
    {
        return $this->wpdb->query($query);
    }

    /**
     * Retrieve multiple rows from the database.
     *
     * @param string $query The SQL query to execute.
     * @return array<object>|object|null Query results or null when no results exist.
     */
    public function getResults(string $query): array|object|null
    {
        return $this->wpdb->get_results($query);
    }

    /**
     * Retrieve a single scalar value from the database.
     *
     * Typically used for aggregate queries such as COUNT(),
     * MAX(), MIN(), or fetching a single column value.
     *
     * @param string $query The SQL query to execute.
     * @return string|null The retrieved value or null if no result exists.
     */
    public function getVar(string $query): ?string
    {
        return $this->wpdb->get_var($query);
    }
    #endregion

    #region Data Manipulation
    /**
     * Delete rows from a database table.
     *
     * @param string $table The target table name.
     * @param array<string, mixed> $whereValues WHERE clause conditions.
     * @param array<int, string>|null $whereFormat Optional WHERE value formats.
     * @return bool|int False on failure or the number of affected rows.
     */
    public function delete(
        string $table,
        array $whereValues,
        ?array $whereFormat = null
    ): bool|int {
        return $this->wpdb->delete($table, $whereValues, $whereFormat);
    }

    /**
     * Insert a single row into a database table.
     *
     * @param string $table The target table name.
     * @param array<string, mixed> $data Column values to insert.
     * @return bool|int False on failure or the inserted row ID.
     */
    public static function insert(string $table, array $data): bool|int
    {
        global $wpdb;

        $inserted = $wpdb->insert($table, $data);

        if (!$inserted) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update existing rows in a database table.
     *
     * @param string $table The target table name.
     * @param array<string, mixed> $data Updated column values.
     * @param array<string, mixed> $whereValues WHERE clause conditions.
     * @return bool|int False on failure or the number of affected rows.
     */
    public function update(
        string $table,
        array $data,
        array $whereValues
    ): bool|int {
        $updated = $this->wpdb->update($table, $data, $whereValues);

        if (!$updated) {
            return false;
        }

        return $updated;
    }

    /**
     * Insert multiple rows into a database table using a single query.
     *
     * This method dynamically generates a bulk INSERT statement
     * and prepares all values using WordPress placeholders.
     *
     * @param string $tableName The target table name.
     * @param array<int, array<string, mixed>> $data Rows to insert.
     * @return bool|int False on failure or the number of affected rows.
     */
    public function insertMultiple(string $tableName, array $data): bool|int
    {
        if (empty($data)) {
            return false;
        }

        $firstItem    = $data[0];
        $columns      = array_keys($firstItem);
        $columnsSql   = implode(', ', $columns);
        $values       = [];
        $placeholders = [];

        foreach ($data as $item) {
            foreach ($item as $value) {
                $values[] = $value;
            }

            $placeholders[] = '(' . implode(', ', array_fill(0, count($item), '%s')) . ')';
        }

        $values_sql = implode(', ', $placeholders);

        $sql = "INSERT INTO $tableName ($columnsSql) VALUES $values_sql";

        return $this->wpdb->query(
            $this->wpdb->prepare($sql, $values)
        );
    }
    #endregion
}
