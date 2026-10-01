<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Routing\Support;

use function array_values;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function preg_replace_callback;
use function str_replace;

/**
 * Minimal stand-in for the WordPress global $wpdb object.
 *
 * The Database manager type-hints the global `wpdb` class, so a bare
 * instance is enough to let the container resolve database services in
 * a plain PHPUnit process.
 *
 * Every call is recorded in an in-memory log so tests can assert on the
 * exact SQL statements and mutation arguments produced by the framework
 * without booting a real WordPress database.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class WPDB
{
    /**
     * WordPress table prefix.
     */
    public string $prefix = 'wp_';

    /**
     * Insert id reported after a successful insert() call.
     *
     * @var int|false
     */
    public int|false $insert_id = 0;

    /**
     * Insert id assigned by the next successful insert() call.
     */
    public int $nextInsertId = 1;

    /**
     * Number of affected rows reported by query(), update() and delete().
     */
    public int $affectedRows = 1;

    /**
     * When true, the next mutating call fails and returns false.
     */
    public bool $failNext = false;

    /**
     * Charset and collation clause returned by get_charset_collate().
     */
    public string $charsetCollate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

    /**
     * When true, the next prepare() call fails and returns false.
     */
    public bool $failPrepare = false;

    /**
     * Error message reported by the last failed query.
     */
    public string $last_error = 'query failed';

    /**
     * Rows returned by get_results() and get_row().
     *
     * @var array<int, object>
     */
    public array $results = [];

    /**
     * Optional resolver consulted by get_results() before falling back to $results.
     *
     * Receives the executed query and returns the raw result set, so tests can
     * reproduce the object or null results a failing query may produce.
     *
     * @var (callable(string): (array<int, object>|object|null))|null
     */
    public mixed $resultsResolver = null;

    /**
     * Value returned by get_var().
     */
    public mixed $varValue = null;

    /**
     * Optional resolver consulted by get_var() before falling back to $varValue.
     *
     * Receives the executed query and returns the value that query reports,
     * so tests can answer metadata queries one by one.
     *
     * @var (callable(string): mixed)|null
     */
    public mixed $varResolver = null;

    /**
     * Log of every query passed to prepare(), query() or a getter.
     *
     * @var list<string>
     */
    public array $queries = [];

    /**
     * Log of insert() calls as [table, data] pairs.
     *
     * @var list<array{0:string, 1:array<string, mixed>}>
     */
    public array $inserts = [];

    /**
     * Log of update() calls as [table, data, where] triples.
     *
     * @var list<array{0:string, 1:array<string, mixed>, 2:array<string, mixed>}>
     */
    public array $updates = [];

    /**
     * Log of delete() calls as [table, where] pairs.
     *
     * @var list<array{0:string, 1:array<string, mixed>}>
     */
    public array $deletes = [];

    /**
     * Restore the mock to its initial state.
     */
    public function reset(): void
    {
        $this->insert_id      = 0;
        $this->nextInsertId   = 1;
        $this->affectedRows   = 1;
        $this->failNext       = false;
        $this->failPrepare    = false;
        $this->last_error     = '';
        $this->charsetCollate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        $this->results      = [];
        $this->resultsResolver = null;
        $this->varValue     = null;
        $this->varResolver  = null;
        $this->queries      = [];
        $this->inserts      = [];
        $this->updates      = [];
        $this->deletes      = [];
    }

    /**
     * Simulate wpdb::prepare() by substituting %s, %d and %f placeholders.
     *
     * Accepts both variadic bindings and the single-array form used by
     * the Database manager. Every received query is added to the log.
     *
     * @param string $query SQL query with placeholders.
     * @param mixed ...$args Query bindings.
     * @return string|false The prepared query, or false on failure.
     */
    public function prepare(string $query, mixed ...$args): string|false
    {
        $this->queries[] = $query;

        $failed = $this->failPrepare;
        $this->failPrepare = false;

        if ($failed) {
            return false;
        }

        if ($args === []) {
            return $query;
        }

        if (count($args) === 1 && is_array($args[0])) {
            $args = array_values($args[0]);
        }

        $index    = 0;
        $prepared = preg_replace_callback(
            '/%(?:[dfis])|%%/',
            static function (array $match) use (&$index, $args): string {
                if ($match[0] === '%%') {
                    return '%';
                }

                $value = $args[$index] ?? null;
                $index++;

                if ($value === null) {
                    return "''";
                }

                if (is_int($value) || is_float($value)) {
                    return (string) $value;
                }

                if (!is_scalar($value)) {
                    return "''";
                }

                return "'" . str_replace("'", "\\'", (string) $value) . "'";
            },
            $query
        );

        return $prepared ?? false;
    }

    /**
     * Simulate wpdb::query().
     *
     * @param string $query SQL query to execute.
     * @return int|false The affected row count, or false on failure.
     */
    public function query(string $query): int|false
    {
        $this->queries[] = $query;

        $failed = $this->failNext;
        $this->failNext = false;

        if ($failed) {
            $this->last_error = 'query failed';

            return false;
        }

        return $this->affectedRows;
    }

    /**
     * Simulate wpdb::get_results().
     *
     * @param string|null $query SQL query to execute.
     * @param string $output Unused output type flag kept for signature parity.
     * @return array<int, object>|object|null The configured result rows, if any.
     */
    public function get_results(?string $query = null, string $output = 'OBJECT'): array|object|null
    {
        $this->queries[] = (string) $query;

        if ($this->resultsResolver !== null) {
            return ($this->resultsResolver)($query ?? '');
        }

        return $this->results;
    }

    /**
     * Simulate wpdb::get_row().
     *
     * @param string|null $query SQL query to execute.
     * @param string $output Unused output type flag kept for signature parity.
     * @param int $y Unused row offset kept for signature parity.
     * @return object|null The first configured row, if any.
     */
    public function get_row(?string $query = null, string $output = 'OBJECT', int $y = 0): ?object
    {
        $this->queries[] = (string) $query;

        return $this->results[0] ?? null;
    }

    /**
     * Simulate wpdb::get_var().
     *
     * @param string|null $query SQL query to execute.
     * @param int $x Unused column offset kept for signature parity.
     * @param int $y Unused row offset kept for signature parity.
     * @return mixed The configured scalar value.
     */
    public function get_var(?string $query = null, int $x = 0, int $y = 0): mixed
    {
        $this->queries[] = (string) $query;

        $resolver = $this->varResolver;

        if ($resolver !== null) {
            return $resolver((string) $query);
        }

        return $this->varValue;
    }

    /**
     * Simulate wpdb::get_col().
     *
     * @param string|null $query SQL query to execute.
     * @param int $x Unused column offset kept for signature parity.
     * @return array<int, mixed> An empty column.
     */
    public function get_col(?string $query = null, int $x = 0): array
    {
        $this->queries[] = (string) $query;

        return [];
    }

    /**
     * Simulate wpdb::insert().
     *
     * @param string $table Target table name.
     * @param array<string, mixed> $data Column values to insert.
     * @param string|array<int, string>|null $format Unused format spec kept for parity.
     * @return int|false 1 on success, or false on failure.
     */
    public function insert(string $table, array $data, string|array|null $format = null): int|false
    {
        $this->inserts[] = [$table, $data];

        $failed = $this->failNext;
        $this->failNext = false;

        if ($failed) {
            $this->last_error = 'query failed';
            return false;
        }

        $this->insert_id = $this->nextInsertId;

        return 1;
    }

    /**
     * Simulate wpdb::update().
     *
     * @param string $table Target table name.
     * @param array<string, mixed> $data Column values to update.
     * @param array<string, mixed> $where WHERE conditions.
     * @param string|array<int, string>|null $format Unused format spec kept for parity.
     * @param string|array<int, string>|null $whereFormat Unused format spec kept for parity.
     * @return int|false The affected row count, or false on failure.
     */
    public function update(
        string $table,
        array $data,
        array $where,
        string|array|null $format = null,
        string|array|null $whereFormat = null
    ): int|false {
        $this->updates[] = [$table, $data, $where];

        $failed = $this->failNext;
        $this->failNext = false;

        if ($failed) {
            $this->last_error = 'query failed';
            return false;
        }

        return $this->affectedRows;
    }

    /**
     * Simulate wpdb::delete().
     *
     * @param string $table Target table name.
     * @param array<string, mixed> $where WHERE conditions.
     * @param string|array<int, string>|null $whereFormat Unused format spec kept for parity.
     * @return int|false The affected row count, or false on failure.
     */
    public function delete(string $table, array $where, string|array|null $whereFormat = null): int|false
    {
        $this->deletes[] = [$table, $where];

        $failed = $this->failNext;
        $this->failNext = false;

        if ($failed) {
            $this->last_error = 'query failed';
            return false;
        }

        return $this->affectedRows;
    }

    /**
     * Simulate wpdb::get_charset_collate().
     *
     * @return string The charset and collation clause appended to DDL statements.
     */
    public function get_charset_collate(): string
    {
        return $this->charsetCollate;
    }
}
