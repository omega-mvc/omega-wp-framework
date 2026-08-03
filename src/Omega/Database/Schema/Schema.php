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

namespace Omega\Database\Schema;

/**
 * Schema Facade
 *
 * Static entry point for database schema definition and modification.
 *
 * This facade provides a high-level API for managing database structures
 * through a Blueprint-based system. It abstracts the underlying SQL generation
 * and execution logic, allowing developers to define schema changes using
 * expressive PHP callbacks instead of raw SQL.
 *
 * Responsibilities:
 * - Creating database tables
 * - Modifying existing tables
 * - Dropping tables safely
 *
 * The Schema facade delegates all structural logic to the Blueprint class,
 * which acts as a schema builder and SQL compiler.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Schema
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class Schema
{
	#region Schema Operation
    /**
     * Create a new database table.
     *
     * This method initializes a Blueprint in "create" mode, allowing the
     * definition of columns, indexes, and constraints through a callback.
     * Once the callback is executed, the schema is compiled and run against
     * the database.
     *
     * @param string $table The name of the table to create (without prefix).
     * @param callable(Blueprint): void $callback Callback used to define table structure.
     * @return void
     */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->setCreate();
        $callback($blueprint);
        $blueprint->run();
    }

    /**
     * Drop a database table if it exists.
     *
     * Executes a direct SQL query against the underlying database connection,
     * removing the specified table including its data and structure.
     *
     * ⚠️ This operation is destructive and cannot be undone.
     *
     * @param string $table The name of the table to drop (without prefix).
     * @return void
     */
    public static function drop(string $table): void
    {
        global $wpdb;

	    /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
	    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
    }

    /**
     * Modify an existing database table.
     *
     * Initializes a Blueprint in "alter" mode, allowing modifications to an
     * existing table structure such as adding or dropping columns, indexes,
     * and constraints.
     *
     * The provided callback receives the Blueprint instance used to define
     * schema changes, which are then compiled and executed.
     *
     * @param string $table The name of the table to modify (without prefix).
     * @param callable(Blueprint): void $callback Callback used to define schema elements.
     * @return void
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $blueprint->run();
    }
	#endregion
}
