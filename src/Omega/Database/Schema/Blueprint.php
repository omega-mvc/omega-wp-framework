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

use Omega\Collection\Collection;
use Omega\Database\Exceptions\SchemaQueryException;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function array_walk;
use function compact;
use function count;
use function esc_sql;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function preg_match;
use function sprintf;
use function str_replace;
use function strtolower;

/**
 * Blueprint
 *
 * Defines and manages table schema operations for database migrations.
 *
 * The Blueprint class acts as a fluent schema builder responsible for
 * describing table structures, columns, indexes, and foreign key constraints.
 * It supports both table creation and alteration workflows through a unified API.
 *
 * Each column definition is internally stored as a ColumnDefinition instance,
 * while schema commands such as dropping columns or indexes are tracked
 * separately and executed during the migration process.
 *
 * This implementation is schema builder while being adapted for WordPress database
 * compatibility and runtime simplicity.
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
class Blueprint
{
    #region Properties
    /** @var ColumnDefinition[] Registered column definitions for the current table blueprint. */
    protected array $columns = [];

    /** @var string Current schema operation command, such as create or alter. */
    protected string $command = 'alter';

    /**
     * @var array<int, array{0: string, 1: string, 2?: array<int|string, string>}|ForeignKeyDefinition>
     *     Registered schema commands and foreign key definitions.
     */
    protected array $commands = [];
    #endregion

    #region Lifecycle
    /**
     * Create a new schema blueprint instance.
     *
     * Initializes the blueprint for the specified database table.
     * The table name is later used when generating schema SQL statements.
     *
     * @param string $table Database table name handled by the blueprint.
     * @return void
     */
    public function __construct(protected string $table)
    {
    }

    /**
     * Mark the blueprint as a table creation operation.
     *
     * Changes the internal command state from "alter" to "create",
     * causing the blueprint to generate a CREATE TABLE statement.
     *
     * @return void
     */
    public function setCreate(): void
    {
        $this->command = 'create';
    }
    #endregion

    #region Primary Keys
    /**
     * Create a new auto-incrementing primary key column.
     *
     * This is a convenience helper that creates an unsigned big integer
     * column with auto-increment enabled and marks it as the table primary key.
     *
     * @param string $column Name of the primary key column.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column)->primary();
    }
    #endregion

    #region Column Genertion
    /**
     * Generate the SQL definition for a single column.
     *
     * Converts a column definition object into its corresponding SQL fragment,
     * including type declarations, nullability, default values, and modifiers.
     *
     * @param ColumnDefinition $column Column definition instance to convert into SQL.
     * @return string Generated SQL fragment for the column definition.
     */
    private function generateSingleColumnSql(ColumnDefinition $column): string
    {
        return sprintf(
            '`%s`%s%s%s%s',
            $column->getName(),
            $this->columnTypeSql($column),
            $this->columnUnsignedSql($column),
            $this->columnNullabilitySql($column),
            $this->columnAutoIncrementSql($column)
        );
    }

    /**
     * Generate the SQL type fragment of a column.
     *
     * Unknown types fall back to a plain text column, and string columns
     * carry their configured length.
     *
     * @param ColumnDefinition $column Column definition instance to convert into SQL.
     * @return string Generated SQL type fragment.
     */
    private function columnTypeSql(ColumnDefinition $column): string
    {
        $types = [
            'bigInteger' => ' bigint(20)',
            'integer'    => ' int(11)',
            'boolean'    => ' tinyint(1)',
            'timestamp'  => ' timestamp',
            'dateTime'   => ' datetime',
            'text'       => ' text',
            'longText'   => ' longtext',
            'json'       => ' json',
        ];

        if ($column->getType() === 'string') {
            return sprintf(' varchar(%d)', $column->getLength() ?? 255);
        }

        return $types[$column->getType()] ?? ' text';
    }

    /**
     * Generate the unsigned fragment of a column.
     *
     * Only integer columns support the unsigned modifier.
     *
     * @param ColumnDefinition $column Column definition instance to inspect.
     * @return string The unsigned fragment, or an empty string.
     */
    private function columnUnsignedSql(ColumnDefinition $column): string
    {
        if (!in_array($column->getType(), ['integer', 'bigInteger'], true)) {
            return '';
        }

        return $column->isUnsigned() ? ' unsigned' : '';
    }

    /**
     * Generate the nullability and default fragments of a column.
     *
     * Nullable columns default to NULL, while the remaining ones are
     * declared NOT NULL and optionally carry their default value.
     *
     * @param ColumnDefinition $column Column definition instance to inspect.
     * @return string Generated nullability fragment.
     */
    private function columnNullabilitySql(ColumnDefinition $column): string
    {
        if ($column->isNullable()) {
            return ' DEFAULT NULL';
        }

        $default = $column->getDefault();

        if ($default === null) {
            return ' NOT NULL';
        }

        if (! is_scalar($default)) {
            return ' NOT NULL DEFAULT \'\'';
        }

        $defaultString = (string) $default;
        $lower         = strtolower($defaultString);

        if ('current_timestamp' === $lower) {
            return ' NOT NULL DEFAULT CURRENT_TIMESTAMP';
        }

        // Boolean columns are emitted as quoted '0'/'1' literals for tinyint(1).
        if ('boolean' === $column->getType() && ($defaultString === '0' || $defaultString === '1')) {
            return " NOT NULL DEFAULT '" . esc_sql($defaultString) . "'";
        }

        if (preg_match('/^-?(?:[1-9]\d*|0)$/', $defaultString)) {
            return ' NOT NULL DEFAULT ' . $defaultString;
        }

        return " NOT NULL DEFAULT '" . esc_sql($defaultString) . "'";
    }

    /**
     * Generate the auto increment fragment of a column.
     *
     * @param ColumnDefinition $column Column definition instance to inspect.
     * @return string The auto increment fragment, or an empty string.
     */
    private function columnAutoIncrementSql(ColumnDefinition $column): string
    {
        if (!$column->isAutoIncrement()) {
            return '';
        }

        return in_array($column->getType(), ['bigInteger', 'unsignedBigInteger', 'bigIncrements'], true)
            ? ' AUTO_INCREMENT'
            : '';
    }

    /**
     * Prepare all column and constraint SQL definitions for execution.
     *
     * Builds the final SQL fragments for columns, indexes, unique constraints,
     * primary keys, and foreign key definitions registered in the blueprint.
     *
     * @return array<int, string> Array of SQL column and constraint definitions.
     */
    private function prepareColumns(): array
    {
        return array_values(array_merge(
            array_map(
                fn (ColumnDefinition $column): string => $this->generateSingleColumnSql($column),
                $this->columns
            ),
            $this->uniqueKeysSql(),
            $this->indexKeysSql(),
            $this->primaryKeySql(),
            $this->commandsSql()
        ));
    }

    /**
     * Build the UNIQUE KEY fragment of every unique column.
     *
     * @return array<int, string> The generated unique key fragments.
     */
    private function uniqueKeysSql(): array
    {
        return array_values(array_map(
            static fn (ColumnDefinition $column): string => sprintf('UNIQUE KEY (`%s`)', $column->getName()),
            array_filter($this->columns, static fn (ColumnDefinition $column): bool => $column->isUnique())
        ));
    }

    /**
     * Build the KEY fragment of every indexed column.
     *
     * Columns already covered by a unique or primary key are skipped.
     *
     * @return array<int, string> The generated index fragments.
     */
    private function indexKeysSql(): array
    {
        return array_values(array_map(
            static fn (ColumnDefinition $column): string => sprintf('KEY (`%s`)', $column->getName()),
            array_filter(
                $this->columns,
                static fn (ColumnDefinition $column): bool
                    => $column->isIndex() && !$column->isUnique() && !$column->isPrimary()
            )
        ));
    }

    /**
     * Build the PRIMARY KEY fragment of every primary column.
     *
     * @return array<int, string> The generated primary key fragment, if any.
     */
    private function primaryKeySql(): array
    {
        $primaryKey = array_values(array_map(
            static fn (ColumnDefinition $column): string => $column->getName(),
            array_filter($this->columns, static fn (ColumnDefinition $column): bool => $column->isPrimary())
        ));

        if ($primaryKey === []) {
            return [];
        }

        return [sprintf('PRIMARY KEY (%s)', implode(', ', $primaryKey))];
    }

    /**
     * Build the SQL fragment of every registered command.
     *
     * Index, unique and foreign key commands are rendered in registration
     * order; the remaining commands have no column definition to render.
     *
     * @return array<int, string> The generated command fragments.
     */
    private function commandsSql(): array
    {
        return array_values(array_filter(
            array_map(
                fn (array|ForeignKeyDefinition $command): ?string => $this->commandSql($command),
                $this->commands
            ),
            static fn (?string $sql): bool => $sql !== null
        ));
    }

    /**
     * Build the SQL fragment of a single registered command.
     *
     * @param array{0: string, 1: string, 2?: array<int|string, string>}|ForeignKeyDefinition $command
     *        The registered command to render.
     * @return string|null The generated fragment, or null when the command is not renderable.
     */
    private function commandSql(array|ForeignKeyDefinition $command): ?string
    {
        if ($command instanceof ForeignKeyDefinition) {
            return $command->getForeignKeySql();
        }

        if (!in_array($command[0], ['index', 'unique'], true)) {
            return null;
        }

        $keyword = 'unique' === $command[0] ? 'UNIQUE KEY' : 'KEY';

        return sprintf(
            '%s `%s` (%s)',
            $keyword,
            $command[1],
            $this->quoteIndexColumns($command[2] ?? [])
        );
    }
    #endregion

    #region Schema Inspection
    /**
     * Determine whether a database table exists.
     *
     * Executes a SHOW TABLES query against the current WordPress database
     * connection to verify if the specified table is present.
     *
     * @param string $tableName Fully qualified database table name.
     * @return bool True if the table exists, otherwise false.
     */
    public function tableExists(string $tableName): bool
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));

        return $exists !== null;
    }

    /**
     * Determine whether a column exists on the specified table.
     *
     * Executes a SHOW COLUMNS query against the database schema
     * to verify if the given column is defined on the table.
     *
     * @param string $tableName Fully qualified database table name.
     * @param string $columnName Name of the column to check.
     * @return bool True if the column exists, otherwise false.
     */
    private function columnExists(string $tableName, string $columnName): bool
    {
        /** @var \wpdb $wpdb */
        global $wpdb;
        /** @var literal-string $query */
        $query  = "SHOW COLUMNS FROM `{$tableName}` LIKE %s";

        $exists = $wpdb->get_var($wpdb->prepare($query, $columnName));

        return $exists !== null;
    }

    /**
     * Determine whether an index exists on the specified table.
     *
     * Executes a SHOW INDEX query against the database schema
     * to verify if the given index is present on the table.
     *
     * @param string $tableName Fully qualified database table name.
     * @param string $indexName Name of the index to check.
     * @return bool True if the index exists, otherwise false.
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        /** @var \wpdb $wpdb */
        global $wpdb;
        /** @var literal-string $query */
        $query  = "SHOW INDEX FROM `{$tableName}` WHERE Key_name = %s";

        $exists = $wpdb->get_var($wpdb->prepare($query, $indexName));

        return $exists !== null;
    }
    #endregion

    #region Schema Execution
    /**
     * Execute the schema blueprint against the database.
     *
     * Depending on the current command state, this method generates
     * and executes either CREATE TABLE or ALTER TABLE statements.
     *
     * Existing tables and columns are automatically checked before
     * attempting schema modifications.
     *
     * @return void
     */
    public function run(): void
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        if ($this->command === 'create') {
            $tableName = $wpdb->prefix . $this->table;

            if ($this->tableExists($tableName)) {
                return;
            }

            $columnsSql = $this->prepareColumns();

            $columnsDef = implode(",\n  ", $columnsSql);

            // Pin the engine instead of inheriting the server default: foreign keys are silently
            // ignored by MyISAM, and a table created on a MyISAM-defaulting host can never be
            // referenced by one, which fails the child's CREATE with errno 150.
            $sql = "CREATE TABLE `$tableName` (\n  $columnsDef\n) ENGINE=InnoDB {$wpdb->get_charset_collate()};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $this->query($sql, $tableName);
        } else {
            $tableName = $wpdb->prefix . $this->table;

            $this->runAlterCommands($tableName);
            $this->addMissingColumns($tableName);
            $this->runIndexCommands($tableName);
        }
    }

    /**
     * Add every declared column that is not present on the table yet.
     *
     * @param string $tableName Fully qualified database table name.
     * @return void
     */
    private function addMissingColumns(string $tableName): void
    {
        array_walk(
            $this->columns,
            function (ColumnDefinition $column) use ($tableName): void {
                $this->addMissingColumn($tableName, $column);
            }
        );
    }

    /**
     * Add a single column when the table does not have it yet.
     *
     * @param string           $tableName        Fully qualified database table name.
     * @param ColumnDefinition $columnDefinition The column to add.
     * @return void
     */
    private function addMissingColumn(string $tableName, ColumnDefinition $columnDefinition): void
    {
        if ($this->columnExists($tableName, $columnDefinition->getName())) {
            return;
        }

        $column = $this->generateSingleColumnSql($columnDefinition);

        $sql = "ALTER TABLE `$tableName` ADD $column" . $this->afterClause($columnDefinition) . ';';

        $this->query($sql, $tableName);
    }

    /**
     * Build the AFTER clause positioning a column after another one.
     *
     * @param ColumnDefinition $column The column being added.
     * @return string The AFTER clause, or an empty string when unpositioned.
     */
    private function afterClause(ColumnDefinition $column): string
    {
        $after = $column->getAfter();

        return $after === null ? '' : " AFTER `$after`";
    }

    /**
     * Execute a schema SQL statement and fail fast if the database reports an error.
     *
     * WordPress database operations normally signal failure by returning false instead
     * of throwing an exception. Without checking that return value, failed CREATE or
     * ALTER statements would be silently ignored and the current migration could still
     * be recorded as successfully applied, leaving the database schema permanently out
     * of sync. This helper converts database failures into exceptions so the migration
     * is never marked as completed unless every schema operation succeeds.
     *
     * @param string $sql SQL statement to execute.
     * @param string $tableName Database table involved in the operation.
     * @return void
     * @throws SchemaQueryException When the SQL statement cannot be executed.
     */
    private function query(string $sql, string $tableName): void
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        if (false === $wpdb->query($sql)) {
            throw new SchemaQueryException(
                sprintf('Schema statement failed for table %s: %s', $tableName, $wpdb->last_error)
            );
        }
    }
    #endregion

    #region Alter Commands
    /**
     * Execute queued ALTER TABLE commands.
     *
     * Processes schema alteration commands such as dropping columns
     * or indexes before adding new column definitions.
     *
     * @param string $tableName Fully qualified database table name.
     * @return void
     */
    private function runAlterCommands(string $tableName): void
    {
        $commands = $this->dropCommands();

        array_walk(
            $commands,
            /**
             * @param array{0: 'dropColumn'|'dropIndex'|'dropUnique', 1: string, 2?: array<int|string, string>} $command
             */
            function (array $command) use ($tableName): void {
                $this->runAlterCommand($tableName, $command);
            }
        );
    }

    /**
     * Collect the queued drop commands, ignoring every other command.
     *
     * @return list<array{0: 'dropColumn'|'dropIndex'|'dropUnique', 1: string, 2?: array<int|string, string>}>
     *     The registered drop commands.
     */
    private function dropCommands(): array
    {
        $arrays = array_filter($this->commands, static fn (mixed $command): bool => is_array($command));

        return array_values(array_filter(
            $arrays,
            static fn (array $command): bool => (
                in_array($command[0], ['dropColumn', 'dropIndex', 'dropUnique'], true)
                && !empty($command[1])
            )
        ));
    }

    /**
     * Execute a single drop command against the table.
     *
     * Drop column and drop index/unique commands are only issued when the
     * target object is still present in the database.
     *
     * @param string $tableName Fully qualified database table name.
     * @param array{0: 'dropColumn'|'dropIndex'|'dropUnique', 1: string, 2?: array<int|string, string>} $command
     *        The registered drop command to execute.
     * @return void
     */
    private function runAlterCommand(string $tableName, array $command): void
    {
        if ($command[0] === 'dropColumn') {
            $this->dropExistingColumn($tableName, $command);

            return;
        }

        $this->dropExistingIndex($tableName, $command);
    }

    /**
     * Drop a column when the table still exposes it.
     *
     * @param string $tableName Fully qualified database table name.
     * @param array{0: 'dropColumn'|'dropIndex'|'dropUnique', 1: string, 2?: array<int|string, string>} $command
     *        The registered drop column command.
     * @return void
     */
    private function dropExistingColumn(string $tableName, array $command): void
    {
        if (!$this->columnExists($tableName, (string) $command[1])) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $wpdb->query("ALTER TABLE `$tableName` DROP COLUMN `{$command[1]}`;");
    }

    /**
     * Drop an index when the table still exposes it.
     *
     * @param string $tableName Fully qualified database table name.
     * @param array{0: 'dropColumn'|'dropIndex'|'dropUnique', 1: string, 2?: array<int|string, string>} $command
     *        The registered drop index command.
     * @return void
     */
    private function dropExistingIndex(string $tableName, array $command): void
    {
        if (!$this->indexExists($tableName, (string) $command[1])) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $wpdb->query("ALTER TABLE `$tableName` DROP INDEX `{$command[1]}`;");
    }

    private function runIndexCommands(string $tableName): void
    {
        $commands = $this->indexCommands();

        array_walk(
            $commands,
            /**
             * @param array{0: 'index'|'unique', 1: string, 2?: array<int|string, string>} $command
             */
            function (array $command) use ($tableName): void {
                $this->runIndexCommand($tableName, $command);
            }
        );
    }

    /**
     * Collect the queued index and unique commands, ignoring every other command.
     *
     * @return list<array{0: 'index'|'unique', 1: string, 2?: array<int|string, string>}>
     *     The registered index commands.
     */
    private function indexCommands(): array
    {
        $arrays = array_filter($this->commands, static fn (mixed $command): bool => is_array($command));

        return array_values(array_filter(
            $arrays,
            static fn (array $command): bool => (
                in_array($command[0], ['index', 'unique'], true)
                && !empty($command[1])
            )
        ));
    }

    /**
     * Execute a single index or unique command against the table.
     *
     * The statement is only issued when the index is not present yet.
     *
     * @param string $tableName Fully qualified database table name.
     * @param array{0: 'index'|'unique', 1: string, 2?: array<int|string, string>} $command
     *        The registered index command to execute.
     * @return void
     */
    private function runIndexCommand(string $tableName, array $command): void
    {
        $indexName = $command[1];

        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        $keyword = 'unique' === $command[0] ? 'UNIQUE INDEX' : 'INDEX';

        /** @var \wpdb $wpdb */
        global $wpdb;

        $wpdb->query(
            "ALTER TABLE `$tableName` ADD $keyword `$indexName` ("
            . $this->quoteIndexColumns($command[2] ?? [])
            . ");"
        );
    }
    #endregion

    #region Column Definitions
    /**
     * Create a new auto-incrementing unsigned big integer column.
     *
     * This helper is commonly used for defining primary key columns
     * compatible with large auto-incrementing identifiers.
     *
     * @param string $column Name of the column to create.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Create a new unsigned big integer column.
     *
     * Optionally enables auto-increment behavior for the column definition.
     *
     * @param string $column Name of the column to create.
     * @param bool $autoIncrement Indicates whether the column should auto increment.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned integer column.
     *
     * Optionally enables auto-increment behavior for the column definition.
     *
     * @param string $column Name of the column to create.
     * @param bool $autoIncrement Indicates whether the column should auto increment.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new timestamp column.
     *
     * If no precision is provided, the default schema precision
     * configured by the blueprint will be applied.
     *
     * @param string $column Name of the column to create.
     * @param int|null $precision Optional fractional seconds precision.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function timestamp(string $column, ?int $precision = null): ColumnDefinition
    {
        $precision ??= $this->defaultTimePrecision();

        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a new datetime column.
     *
     * If no precision is provided, the default schema precision
     * configured by the blueprint will be applied.
     *
     * @param string $column Name of the column to create.
     * @param int|null $precision Optional fractional seconds precision.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function dateTime(string $column, ?int $precision = null): ColumnDefinition
    {
        $precision ??= $this->defaultTimePrecision();

        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * Create a new text column.
     *
     * The generated column is suitable for medium-length textual content.
     *
     * @param string $column Name of the column to create.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a new long text column.
     *
     * The generated column is suitable for storing large textual content.
     *
     * @param string $column Name of the column to create.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a new JSON column.
     *
     * The generated column is intended for storing structured JSON data.
     *
     * @param string $column Name of the column to create.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new boolean column.
     *
     * Internally the column is represented using a tiny integer type.
     *
     * @param string $column Name of the column to create.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new UUID column.
     *
     * UUID values are stored internally as fixed-length string columns
     * with a length of 36 characters.
     *
     * @param string $column Name of the column to create.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn('string', $column, ['length' => 36]);
    }

    /**
     * Create a new big integer column.
     *
     * Supports optional unsigned and auto-increment modifiers.
     *
     * @param string $column Name of the column to create.
     * @param bool $autoIncrement Indicates whether the column should auto increment.
     * @param bool $unsigned Indicates whether the column should be unsigned.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function bigInteger(
        string $column,
        bool $autoIncrement = false,
        bool $unsigned = false
    ): ColumnDefinition {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new integer column.
     *
     * Supports optional unsigned and auto-increment modifiers.
     *
     * @param string $column Name of the column to create.
     * @param bool $autoIncrement Indicates whether the column should auto increment.
     * @param bool $unsigned Indicates whether the column should be unsigned.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function integer(
        string $column,
        bool $autoIncrement = false,
        bool $unsigned = false
    ): ColumnDefinition {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new string column.
     *
     * If no length is provided, a default length of 255 characters is used.
     *
     * @param string $column Name of the column to create.
     * @param int|null $length Maximum length of the string column.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function string(string $column, ?int $length = null): ColumnDefinition
    {
        $length = $length ?: 255;

        return $this->addColumn('string', $column, compact('length'));
    }
    #endregion

    #region Time Helpers
    /**
     * Add nullable created_at and updated_at datetime columns.
     *
     * This helper creates timestamp management columns compatible with
     * WordPress database environments by using datetime types internally.
     *
     * @param int|null $precision Optional fractional seconds precision.
     * @return Collection<ColumnDefinition> Collection containing both column definitions.
     */
    public function timestamps(?int $precision = null): Collection
    {
        //change timestamp to dateTime for WordPress compatibility
        return new Collection([
            $this->dateTime('created_at', $precision)->nullable(),
            $this->dateTime('updated_at', $precision)->nullable(),
        ]);
    }

    /**
     * Get the default fractional seconds precision for time columns.
     *
     * This value is used when no explicit precision is provided
     * for timestamp or datetime column definitions.
     *
     * @return int|null Default time precision value.
     */
    protected function defaultTimePrecision(): ?int
    {
        return 0;
    }
    #endregion

    #region Foreign Keys
    /**
     * Define a foreign key constraint for the table.
     *
     * If the previously added column is a foreignId definition,
     * the foreign key command is automatically registered.
     *
     * @param array<int, string>|string $columns Column or columns participating in the constraint.
     * @param string|null $name Optional custom foreign key constraint name.
     * @return ForeignKeyDefinition The configured foreign key definition instance.
     */
    public function foreign(array|string $columns, ?string $name = null): ForeignKeyDefinition
    {
        $foreignInstance = $this->columns[count($this->columns) - 1];

        if ($foreignInstance instanceof ForeignIdColumnDefinition) {
            $command = new ForeignKeyDefinition($this, $foreignInstance->getAttributes());
            $this->commands[] = $command;

            return $command;
        }

        return new ForeignKeyDefinition($this, [
            'columns'   => $columns,
            'name'      => $name,
            'blueprint' => $this,
        ]);
    }

    /**
     * Create a new unsigned foreign ID column.
     *
     * The generated column is configured as an unsigned big integer
     * intended for use with foreign key constraints.
     *
     * @param string $column Name of the foreign ID column.
     * @return ForeignIdColumnDefinition The configured column definition instance.
     */
    public function foreignId(string $column): ForeignIdColumnDefinition
    {
        return $this->addColumnDefinition(new ForeignIdColumnDefinition($this, [
            'type'          => 'bigInteger',
            'name'          => $column,
            'autoIncrement' => false,
            'unsigned'      => true,
        ]));
    }
    #endregion

    #region Indexes
    /**
     * Specify an index for the table.
     *
     * @param  string|array<int|string, string>  $columns
     * @param  string|null  $name
     * @return $this
     */
    public function index(string|array $columns, ?string $name = null): static
    {
        return $this->indexCommand('index', $columns, $name);
    }

    /**
     * Specify a unique index for the table.
     *
     * @param  string|array<int|string, string>  $columns
     * @param  string|null  $name
     * @return $this
     */
    public function unique(string|array $columns, ?string $name = null): static
    {
        return $this->indexCommand('unique', $columns, $name);
    }

    /**
     * Add a new index command to the blueprint.
     *
     * @param  string  $type
     * @param  string|array<int|string, string>  $columns
     * @param  string|null  $index
     * @return $this
     */
    protected function indexCommand(string $type, string|array $columns, ?string $index = null): static
    {
        $columns = (array) $columns;

        $index = $index ?: $this->createIndexName($type, $columns);

        $this->commands[] = [ $type, $index, $columns ];

        return $this;
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string  $type
     * @param  array<int|string, string>  $columns
     * @return string
     */
    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);

        return str_replace([ '-', '.', '(', ')' ], [ '_', '_', '_', '' ], $index);
    }

    /**
     * Quote index columns, supporting prefix lengths like "column(20)".
     *
     * @param  array<int|string, string>  $columns
     * @return string
     */
    private function quoteIndexColumns(array $columns): string
    {
        $quoted = array_map(
            static function (string $column): string {
                if (preg_match('/^(\w+)\s*\((\d+)\)$/', $column, $matches)) {
                    return "`{$matches[1]}`({$matches[2]})";
                }

                return "`$column`";
            },
            $columns
        );

        return implode(', ', $quoted);
    }
    #endregion

    #region Column Registration
    /**
     * Add a new column definition to the blueprint.
     *
     * Creates a new column definition instance using the provided
     * type, name, and additional configuration parameters.
     *
     * @param string $type Column data type identifier.
     * @param string $name Name of the column to create.
     * @param array<string, mixed> $parameters Additional column configuration parameters.
     * @return ColumnDefinition The configured column definition instance.
     */
    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumnDefinition(new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        ));
    }

    /**
     * Register a column definition within the blueprint.
     *
     * Stores the column definition internally so it can later
     * be included in generated schema SQL statements.
     *
     * @template T of ColumnDefinition
     * @param T $definition Column definition instance to register.
     * @return T The registered column definition instance.
     */
    protected function addColumnDefinition(ColumnDefinition $definition): ColumnDefinition
    {
        $this->columns[] = $definition;

        return $definition;
    }
    #endregion

    #region Drop Operations
    /**
     * Queue a column drop operation for the table.
     *
     * The column removal command will be executed when the blueprint
     * is processed through the schema runner.
     *
     * @param string $column Name of the column to remove.
     * @return static The current blueprint instance.
     */
    public function dropColumn(string $column): static
    {
        $this->commands[] = ['dropColumn', $column];

        return $this;
    }

    /**
     * Indicate that the given index should be dropped.
     *
     * @param  string|array<int|string, string>  $index
     *         Index name or array of columns to resolve the conventional name.
     * @return $this
     */
    public function dropIndex(string|array $index): static
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * Queue a unique index drop operation for the table.
     *
     * The unique index removal command will be executed when
     * the blueprint is processed through the schema runner.
     *
     * @param string $index Name of the unique index to remove.
     * @return static The current blueprint instance.
     */
    public function dropUnique(string $index): static
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * Add a new drop index command to the blueprint.
     *
     * @param  string  $command
     * @param  string  $type
     * @param  string|array<int|string, string>  $index
     * @return $this
     */
    protected function dropIndexCommand(string $command, string $type, string|array $index): static
    {
        if (is_array($index)) {
            $index = $this->createIndexName($type, $index);
        }

        $this->commands[] = [ $command, $index ];

        return $this;
    }
    #endregion

    #region Accessors
    /**
     * Get the table name associated with the blueprint.
     *
     * Returns the raw table name configured for the schema operation,
     * without applying any database prefix.
     *
     * @return string The blueprint table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }
    #endregion
}
