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

namespace Omega\Database\ORM;

use Closure;
use Exception;
use Omega\Collection\Collection;
use Omega\Database\Database;
use Omega\Database\Exceptions\ModelNotFoundException;
use Omega\Database\ORM\Relations\AbstractRelation;
use Omega\Database\ORM\Relations\BelongsTo;
use Omega\Database\ORM\Relations\HasMany;
use Omega\Database\ORM\Relations\HasOne;
use Omega\Paginator\Paginator;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

use function array_fill;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_values;
use function array_walk;
use function call_user_func;
use function count;
use function current_time;
use function implode;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_scalar;
use function is_string;
use function iterator_to_array;
use function max;
use function strtoupper;
use function wp_list_pluck;

/**
 * QueryBuilder
 *
 * Fluent SQL query builder bound to a specific AbstractModel instance.
 *
 * Provides a structured API for building database queries including:
 * - SELECT operations with column selection
 * - WHERE clauses (standard, nested, raw, and relation-based)
 * - JOIN operations
 * - GROUP BY and ORDER BY clauses
 * - LIMIT and OFFSET pagination
 * - Relationship eager loading via "with"
 * - EXISTS subqueries support
 *
 * The builder is tightly coupled with the model's database connection
 * and table configuration, ensuring queries are automatically scoped
 * to the correct table context.
 *
 * This class is not intended to be instantiated directly outside of
 * model query entry points (e.g. Model::query()).
 *
 * @category   Omega
 * @package    Database
 * @subpackage Eloquent
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class QueryBuilder
{
    #region Properties
    /** @var string Database table name associated with the model query. */
    protected string $tableName;

    /** @var Database Database connection instance used to execute queries. */
    protected Database $db;

    /**
     * Collection of WHERE conditions applied to the query.
     *
     * Each entry represents a standard filtering condition and may include:
     * - column: target column name
     * - value: comparison value
     * - operator: SQL operator (e.g. '=', 'IN', 'IS')
     * - method: boolean connector (AND / OR)
     * - table: optional table prefix for joins
     *
     * @var array<int, array{
     *     column: string,
     *     value: mixed,
     *     operator: string,
     *     method?: string,
     *     table?: string
     * }|array{
     *     type: 'Nested',
     *     callback: Closure,
     *     method?: string
     * }|array{
     *     type: 'Raw',
     *     sql: string,
     *     bindings: array<int, mixed>,
     *     method?: string
     * }>
     */
    protected array $whereArray = [];

    /**
     * Collection of EXISTS / NOT EXISTS subquery conditions.
     *
     * Each entry contains a pre-generated SQL subquery and the
     * boolean operator used to combine it with other conditions.
     *
     * @var array<int, array{
     *     sql: string,
     *     method?: string,
     *     not?: bool
     * }>
     */
    protected array $existsArray = [];

    /**
     * Column-to-column comparison conditions.
     *
     * Used for WHERE clauses comparing two database columns
     * instead of column-value comparisons.
     *
     * @var array<int, array{
     *     column_one: string,
     *     operator: string|null,
     *     column_two: string|null,
     *     method?: string
     * }>
     */
    protected array $whereColumnArray = [];

    /**
     * Join definitions applied to the query.
     *
     * Each entry represents an SQL JOIN clause including:
     * - table: target table
     * - local_key: column on the primary table
     * - foreign_key: column on the joined table
     *
     * @var array<int, array{
     *     table: string,
     *     local_key: string,
     *     foreign_key: string
     * }>
     */
    protected array $joinArray = [];

    /** @var array<int, string> List of columns used for GROUP BY clause. */
    protected array $groupBy = [];

    /**
     * Relationship eager loading configuration.
     *
     * Each entry defines a model relationship to be preloaded,
     * including mapping metadata required to resolve foreign keys
     * and hydrate related models.
     *
     * @var array<int, array{
     *     relation: string,
     *     table: string,
     *     foreign_key: string,
     *     local_key: string,
     *     model: class-string<AbstractModel>,
     *     relation_type: class-string<AbstractRelation>
     * }>
     */
    protected array $withArray = [];

    /**
     * Sorting configuration for query results.
     *
     * Each entry defines a column and direction used in ORDER BY clause.
     *
     * @var array<int, array{
     *     column: string,
     *     order: string
     * }>
     */
    protected array $orderBy = [];

    /**
     * Selected columns for the query.
     *
     * Stored as a raw SQL select string (e.g. "*", "id, name, email").
     */
    private string $select = '*';

    /**
     * Maximum number of records to return.
     *
     * Used to restrict result set size.
     */
    private ?int $limit = null;

    /**
     * Number of records to skip before returning results.
     *
     * Used for pagination and result slicing.
     */
    private ?int $offset = null;
    #endregion

    #region Lifecycle
    /**
     * QueryBuilder constructor.
     *
     * Initializes a new query builder instance bound to a specific model.
     * The builder automatically resolves the database connection and table name
     * from the provided model instance.
     *
     * If the model uses soft deletes, a default condition is automatically added
     * to exclude soft-deleted records (deleted_at IS NULL logic).
     *
     * @param AbstractModel $model The model instance used to scope the query.
     */
    public function __construct(protected AbstractModel $model)
    {
        $this->db        = $model->getDatabase();
        $this->tableName = $model->getTableName();

        if ($model->trashed()) {
            $this->whereArray[] = ['column' => 'deleted_at', 'value' => null, 'operator' => 'IS'];
        }
    }
    #endregion

    #region Query Building
    /**
     * Define the columns to be selected in the query.
     *
     * Accepts either a string or an array of column names. When an array is provided,
     * it is converted into a comma-separated list suitable for SQL SELECT statements.
     *
     * @param array<int, string>|string $columns Column name(s) to select.
     * @return QueryBuilder Returns the current query builder instance for chaining.
     */
    public function select(array|string $columns): QueryBuilder
    {
        $this->select = is_array($columns)
            ? implode(', ', $columns)
            : $columns;

        return $this;
    }

    /**
     * Add a WHERE condition to the query.
     *
     * Supports multiple input styles:
     * - Key/value array of conditions
     * - Nested callback conditions
     * - Standard column/operator/value syntax
     *
     * Automatically normalizes operators and supports optional table scoping.
     *
     * @param array<string, mixed>|Closure|mixed $column Column name, conditions array, or nested callback.
     * @param mixed|null $operator SQL operator (e.g. '=', '>', 'IN') or value if omitted.
     * @param mixed|null $value Comparison value.
     * @param string|null $method Boolean operator (AND/OR) used to join conditions.
     * @param string|null $table Optional table name for fully qualified columns.
     * @return static Returns the current query builder instance for chaining.
     */
    public function where(
        mixed $column,
        mixed $operator = null,
        mixed $value = null,
        mixed $method = null,
        mixed $table = null
    ): static {
        if (is_array($column)) {
            array_walk($column, function (mixed $value, int|string $key): void {
                $this->where($key, $value);
            });
            return $this;
        }

        if ($column instanceof Closure) {
            $this->whereArray[] = [
                'type'     => 'Nested',
                'callback' => $column,
                'method'   => $method ?? 'AND'
            ];
            return $this;
        }

        $this->pushWhereCondition($column, $operator, $value, $method, $table);

        return $this;
    }

    /**
     * Store a column comparison condition, normalising NULL comparisons.
     *
     * When the compared value is null the condition is stored as an
     * `IS NULL` / `IS NOT NULL` predicate, which is the only valid SQL form.
     *
     * @param mixed $column The column name (or the value in the two-argument form).
     * @param mixed $operator The SQL operator, or the value when no operator is given.
     * @param mixed $value The comparison value, when an operator is present.
     * @param string|null $method Boolean operator (AND/OR) used to join conditions.
     * @param string|null $table Optional table name for fully qualified columns.
     * @return void
     */
    private function pushWhereCondition(
        mixed $column,
        mixed $operator,
        mixed $value,
        mixed $method,
        mixed $table
    ): void {
        $operatorString = is_scalar($operator) ? (string) $operator : '';

        // A null value is only an explicit NULL comparison when the operator
        // is one that can be applied to NULL. Any other operator carrying a
        // null value is actually the two-argument form `where($column, $value)`.
        if ($value === null && !in_array($operatorString, ['IS', 'IS NOT', '=', '!=', '<>'], true)) {
            $whereOperator = '=';
            $whereValue    = $operator;
        } else {
            $whereOperator = $operatorString !== '' ? $operatorString : '=';
            $whereValue    = $value;
        }

        $where = [
            'column'   => is_scalar($column) ? (string) $column : '',
            'value'    => $whereValue,
            'operator' => $whereOperator
        ];

        if ($whereValue === null) {
            $where['operator'] = in_array($whereOperator, ['IS NOT', '!=', '<>'], true) ? 'IS NOT' : 'IS';
        }

        if ($method) {
            $where['method'] = (string) $method;
        }

        if ($table) {
            $where['table'] = (string) $table;
        }

        $this->whereArray[] = $where;
    }

    /**
     * Add an OR WHERE condition to the query.
     *
     * Accepts either:
     * - a key/value array of conditions
     * - a standard column/operator/value expression
     *
     * Internally delegates to where() using OR as the boolean operator.
     *
     * @param array<string, mixed>|mixed $column Column name or conditions array.
     * @param mixed|null $operator SQL operator or value if omitted.
     * @param mixed|null $value Comparison value.
     * @return QueryBuilder Returns the current query builder instance for chaining.
     */
    public function orWhere(mixed $column, mixed $operator = null, mixed $value = null): QueryBuilder
    {
        if (is_array($column)) {
            array_walk($column, function (mixed $value, int|string $key): void {
                $this->orWhere($key, $value);
            });
            return $this;
        }

        $this->pushWhereCondition($column, $operator, $value, 'OR', null);

        return $this;
    }

    /**
     * Add a WHERE IS NULL condition to the query.
     *
     * Filters results where the specified column contains a NULL value.
     *
     * @param string $column Column name to check for NULL.
     * @return static Returns the current query builder instance for chaining.
     */
    public function whereNull(string $column): static
    {
        $this->pushWhereCondition($column, 'IS', null, null, null);

        return $this;
    }

    /**
     * Add a WHERE IS NOT NULL condition to the query.
     *
     * Filters results where the specified column is not NULL.
     *
     * @param string $column Column name to check for non-NULL values.
     * @return static Returns the current query builder instance for chaining.
     */
    public function whereNotNull(string $column): static
    {
        $this->pushWhereCondition($column, 'IS NOT', null, null, null);

        return $this;
    }

    /**
     * Add a raw WHERE clause to the query.
     *
     * Allows injecting custom SQL fragments directly into the query.
     * Bindings are supported to safely inject dynamic values.
     *
     * @param string $sql Raw SQL condition string.
     * @param array<int, mixed> $bindings Optional parameter bindings for prepared statements.
     * @param string $boolean Boolean operator used to join conditions (AND/OR).
     * @return static Returns the current query builder instance for chaining.
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->whereArray[] = [
            'type'     => 'Raw',
            'sql'      => $sql,
            'bindings' => $bindings,
            'method'   => $boolean
        ];

        return $this;
    }

    /**
     * Add a raw OR WHERE clause to the query.
     *
     * Shortcut for whereRaw() using OR as the boolean operator.
     *
     * @param string $sql Raw SQL condition string.
     * @param array<int, mixed> $bindings Optional parameter bindings for prepared statements.
     * @return static Returns the current query builder instance for chaining.
     */
    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        return $this->whereRaw($sql, $bindings, 'OR');
    }

    /**
     * Add a WHERE IN condition to the query.
     *
     * Filters results where the given column matches any of the provided values.
     *
     * @param string $column Column name to filter.
     * @param array<int, mixed> $values List of values for the IN condition.
     * @return QueryBuilder Returns the current query builder instance for chaining.
     */
    public function whereIn(string $column, array $values = []): QueryBuilder
    {
        $this->where($column, 'IN', $values);

        return $this;
    }

    /**
     * Add a column comparison condition to the query.
     *
     * Compares two columns directly using a SQL operator (e.g. columnA = columnB).
     *
     * @param string $columnOne First column name.
     * @param string|null $operator Comparison operator or second column if omitted.
     * @param string|null $columnTwo Second column name.
     * @param string $method Boolean operator used to join conditions (AND/OR).
     * @return static Returns the current query builder instance for chaining.
     */
    public function whereColumn(
        string $columnOne,
        ?string $operator = null,
        ?string $columnTwo = null,
        string $method = 'AND'
    ): static {
        $this->whereColumnArray[] = [
            'column_one' => $columnOne,
            'operator'   => $columnTwo ? $operator : '=',
            'column_two' => $columnTwo ?? $operator,
            'method'     => $method
        ];

        return $this;
    }

    /**
     * Add a "WHERE HAS" clause to filter records based on related model existence.
     *
     * Builds a subquery on the specified relationship and filters the parent query
     * to include only records that have at least one matching related record.
     *
     * An optional callback can be used to further constrain the related query
     * before it is converted into an EXISTS subquery.
     *
     * @param string $relation Name of the relationship method defined on the model.
     * @param callable(QueryBuilder): void|null $callback Optional callback to modify the related query.
     * @return QueryBuilder Returns the current query builder instance for chaining.
     * @throws ReflectionException Thrown when the relationship method cannot be resolved via reflection.
     */
    public function whereHas(string $relation, ?callable $callback = null): QueryBuilder
    {
        $reflection   = new ReflectionClass($this->model);
        $method       = $reflection->getMethod($relation);
        /** @var AbstractRelation $relation */
        $relation     = $method->invoke($this->model);
        $relatedClass = $relation->getRelatedClass();
        $query        = $relatedClass::query();
        /** @var QueryBuilder $query */

        if ($relation instanceof HasMany) {
            $query->whereColumn(
                $relation->getForeignKey(),
                $this->model->getTableName()
                . '.'
                . $relation->getLocalKey()
            );
        } elseif ($relation instanceof HasOne) {
            $query->whereColumn(
                $relation->getForeignKey(),
                $this->model->getTableName()
                . '.'
                . $relation->getLocalKey()
            );
        } elseif ($relation instanceof BelongsTo) {
            $query->whereColumn(
                $relation->getLocalKey(),
                $this->model->getTableName()
                . '.'
                . $relation->getForeignKey()
            );
        }

        if (is_callable($callback)) {
            $callback($query);
        }

        $sql = $query->generateQuery();

        $this->existsArray[] = [
            'sql'    => $sql,
            'method' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a "WHERE NOT EXISTS" condition for a relationship.
     *
     * Builds a subquery based on the given relationship method and excludes
     * records where the relationship exists, optionally filtered through a callback.
     *
     * @param string $relation Relationship method name defined on the model.
     * @param callable(QueryBuilder): void $callback Callback to customize the related query.
     * @return static Returns the current query builder instance for chaining.
     * @throws ReflectionException Thrown when the relationship method cannot be resolved via reflection.
     */
    public function whereDoesntHave(string $relation, callable $callback): static
    {
        $reflection    = new ReflectionClass($this->model);
        $method        = $reflection->getMethod($relation);
        /** @var AbstractRelation $relation */
        $relation      = $method->invoke($this->model);
        $relatedClass  = $relation->getRelatedClass();
        $query         = $relatedClass::query();
        /** @var QueryBuilder $query */

        if ($relation instanceof HasMany) {
            $query->whereColumn(
                $relation->getForeignKey(),
                $this->model->getTableName()
                . '.'
                . $relation->getLocalKey()
            );
        } elseif ($relation instanceof BelongsTo) {
            $query->whereColumn(
                $relation->getLocalKey(),
                $this->model->getTableName()
                . '.'
                . $relation->getForeignKey()
            );
        }

        $callback($query);

        $sql = $query->generateQuery();

        $this->existsArray[] = [
            'sql'    => $sql,
            'method' => 'AND',
            'not'    => true,
        ];

        return $this;
    }

    /**
     * Add a WHERE condition based on a relationship join.
     *
     * Dynamically resolves the given relationship using reflection and builds
     * a join + where condition against the related table.
     *
     * Supports both HasOne and BelongsTo relationships and automatically
     * adjusts join keys accordingly.
     *
     * @param string $relation Relationship method name on the model.
     * @param string $column Column name in the related table.
     * @param mixed $valueOrOperator Comparison operator or value.
     * @param mixed|null $fieldValue Optional value when using a custom operator.
     * @param string $queryMethod Boolean operator used to join conditions (AND/OR).
     * @return static Returns the current query builder instance for chaining.
     * @throws ReflectionException Thrown when the relationship method cannot be resolved.
     */
    public function whereRelation(
        string $relation,
        string $column,
        mixed $valueOrOperator,
        mixed $fieldValue = null,
        string $queryMethod = 'AND'
    ): static {
        //TODO: Verify and refactor this
        $reflection = new ReflectionClass($this->model);
        $method     = $reflection->getMethod($relation);
        $returnType = $method->getReturnType();

        if ($relation !== $method->getName()) {
            return $this;
        }

        if (!$returnType instanceof ReflectionNamedType) {
            return $this;
        }

        $typeName = $returnType->getName();

        if ($typeName === HasOne::class) {
            /**
             * @var HasOne $hasOne
             * TODO: Verify not implemented
             */
            $hasOne       = $method->invoke($this->model);
            $relatedClass = $hasOne->getRelatedClass();
            $tableName    = $relatedClass::getFullTableName();

            /** @var string $tableName */
            $this->joinArray[] = [
                'table'       => $tableName,
                'foreign_key' => $this->model->getForeignKey() . '_id',
                'local_key'   => 'id',
            ];

            //TODO: Replace by $this->where
            $this->whereArray[] = [
                'method'   => $queryMethod,
                'column'   => $column,
                'table'    => $tableName,
                'value'    => $fieldValue ?? $valueOrOperator,
                'operator' => $this->relationOperator($valueOrOperator, $fieldValue)
            ];

            if ($relatedClass::isTrashed()) {
                $this->whereArray[] = [
                    'column'   => 'deleted_at',
                    'table'    => $tableName,
                    'value'    => null,
                    'operator' => 'IS'
                ];
            }
        } elseif ($typeName === BelongsTo::class) {
            /**
             * @var BelongsTo $belongsTo
             */
            $belongsTo    = $method->invoke($this->model);
            $relatedClass = $belongsTo->getRelatedClass();
            $tableName    = $relatedClass::getFullTableName();

            /** @var string $tableName */
            $this->joinArray[] = [
                'table'       => $tableName,
                'foreign_key' => $belongsTo->getLocalKey(),
                'local_key'   => $belongsTo->getForeignKey()
            ];

            //TODO: Replace by $this->where
            $this->whereArray[] = [
                'method'   => $queryMethod,
                'column'   => $column,
                'table'    => $tableName,
                'value'    => $fieldValue ?? $valueOrOperator,
                'operator' => $this->relationOperator($valueOrOperator, $fieldValue)
            ];

            if ($relatedClass::isTrashed()) {
                $this->whereArray[] = [
                    'column'   => 'deleted_at',
                    'table'    => $tableName,
                    'value'    => null,
                    'operator' => 'IS'
                ];
            }
        }

        return $this;
    }

    /**
     * Resolve the SQL operator of a relation constraint.
     *
     * A custom operator is only honoured when a dedicated field value is given.
     *
     * @param  mixed  $valueOrOperator The comparison operator or value.
     * @param  mixed  $fieldValue      The optional comparison value.
     * @return string The resolved SQL operator.
     */
    private function relationOperator(mixed $valueOrOperator, mixed $fieldValue): string
    {
        $operator = is_scalar($valueOrOperator) ? (string) $valueOrOperator : '=';

        if (!isset($fieldValue)) {
            $operator = '=';
        }

        return $operator;
    }

    /**
     * Add an OR WHERE condition based on a relationship constraint.
     *
     * This is a convenience wrapper around whereRelation() using OR as the boolean operator.
     *
     * @param string $relation Relationship method name on the model.
     * @param string $column Column name within the related table.
     * @param mixed $valueOrOperator Comparison value or operator.
     * @param mixed|null $fieldValue Optional comparison value when using a custom operator.
     * @return static Returns the current query builder instance for chaining.
     * @throws ReflectionException Thrown when the relationship cannot be resolved via reflection.
     */
    public function orWhereRelation(
        string $relation,
        string $column,
        mixed $valueOrOperator,
        mixed $fieldValue = null
    ): static {
        return $this->whereRelation(
            $relation,
            $column,
            $valueOrOperator,
            $fieldValue,
            'OR'
        );
    }

    /**
     * Add GROUP BY clause to the query.
     *
     * Accepts one or more column names and appends them to the GROUP BY statement.
     *
     * @param string ...$columns One or more column names to group by.
     * @return QueryBuilder Returns the current query builder instance for chaining.
     */
    public function groupBy(string ...$columns): QueryBuilder
    {
        $this->groupBy = array_values(array_merge($this->groupBy, $columns));

        return $this;
    }

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param string $column Column name to sort by.
     * @param string $order Sort direction ("asc" or "desc").
     * @return QueryBuilder Current query builder instance for chaining.
     */
    public function orderBy(string $column, string $order = 'asc'): QueryBuilder
    {
        $this->orderBy[] = ['column' => $column, 'order' => $order];

        return $this;
    }

    /**
     * Limit the number of results returned by the query.
     *
     * @param int $limit Maximum number of records to retrieve.
     * @return QueryBuilder Current query builder instance for chaining.
     */
    public function limit(int $limit): QueryBuilder
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set the query result offset.
     *
     * @param int $offset Number of records to skip.
     * @return QueryBuilder Current query builder instance for chaining.
     */
    public function offset(int $offset): QueryBuilder
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Specify relationships to eager load with the query.
     *
     * Accepts a single relationship name or an array of relationships.
     * Each relation is resolved and registered for eager loading.
     *
     * @param string|array<int, string> $relations Relationship name(s) to eager load.
     * @return QueryBuilder Returns the current query builder instance for chaining.
     */
    public function with(array|string $relations): QueryBuilder
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        array_walk($relations, function (string $relation): void {
            $this->addRelationToWith($relation);
        });

        return $this;
    }

    /**
     * Register a single relationship for eager loading.
     *
     * Validates the relationship method, resolves its return type,
     * and stores the configuration required for eager loading execution.
     *
     * Invalid or non-existing relationships are silently ignored.
     *
     * @param string $relation Relationship method name.
     * @return void
     */
    private function addRelationToWith(string $relation): void
    {
        if (empty($relation)) {
            return;
        }

        try {
            $reflection = new ReflectionClass($this->model);

            if (!$reflection->hasMethod($relation)) {
                return;
            }

            $method = $reflection->getMethod($relation);

            if ($relation !== $method->getName()) {
                return;
            }

            $returnType = $method->getReturnType();
            if (!$returnType instanceof ReflectionNamedType) {
                return;
            }

            /** @var AbstractRelation $relationInstance */
            $relationInstance = $method->invoke($this->model);
            $relatedClass     = $relationInstance->getRelatedClass();

            /** @var class-string<AbstractModel> $relatedClass */
            $returnTypeName   = $returnType->getName();
            $relationConfig   = $this->buildRelationConfig($returnTypeName, $relatedClass, $relation);

            if ($relationConfig) {
                $this->withArray[] = $relationConfig;
            }
        } catch (Exception) {
            // Silently ignore invalid relations
            return;
        }
    }

    /**
     * Build the configuration array for an eager-loaded relationship.
     *
     * Generates metadata required to execute eager loading based on
     * the relationship type (HasOne, HasMany, BelongsTo).
     *
     * @param string $relationTypeName Fully qualified relationship class name.
     * @param class-string<AbstractModel> $relatedClass Related model class name.
     * @param string $relation Relationship method name.
     * @return array{
     *     model: class-string<AbstractModel>,
     *     relation: string,
     *     table: string,
     *     foreign_key: string,
     *     local_key: string,
     *     relation_type: class-string<AbstractRelation>
     * }|null Returns relation configuration array or null if unsupported.
     */
    private function buildRelationConfig(
        string $relationTypeName,
        string $relatedClass,
        string $relation
    ): ?array {
        /** @var class-string<AbstractModel> $relatedClass */
        $baseConfig = [
            'model'    => $relatedClass,
            'relation' => $relation,
            'table'    => $relatedClass::getTable(),
        ];

        return match ($relationTypeName) {
            HasOne::class    => array_merge($baseConfig, [
                'foreign_key'   => $this->model->getForeignKey() . '_id',
                'local_key'     => 'id',
                'relation_type' => HasOne::class
            ]),
            BelongsTo::class  => array_merge($baseConfig, [
                'foreign_key'   => 'id',
                'local_key'     => $relatedClass::getForeignKeyStatic(),
                'relation_type' => BelongsTo::class
            ]),
            HasMany::class   => array_merge($baseConfig, [
                'foreign_key'   => $this->model->getForeignKey() . '_id',
                'local_key'     => 'id',
                'relation_type' => HasMany::class
            ]),
            default => null,
        };
    }
    #endregion

    #region Retrieval
    /**
     * Retrieve a single record by primary key.
     *
     * Internally builds a WHERE clause using the model's primary key
     * and returns the first matching record, if any.
     *
     * @param mixed $id Primary key value of the record to retrieve.
     * @return AbstractModel|null Returns the found model instance or null if not found.
     */
    public function find(mixed $id): ?AbstractModel
    {
        return $this->where($this->model->getPrimaryKey(), $id)->first();
    }

    /**
     * Execute the query and return a collection of hydrated model instances.
     *
     * Also resolves eager-loaded relations if defined via `with()`.
     *
     * @return Collection<AbstractModel> Collection of hydrated model instances.
     */
    public function get(): Collection
    {
        $primaryKey = $this->model->getPrimaryKey();
        $queryResults = $this->db->getResults($this->generateQuery());
        $results = $this->iterableResults($queryResults);
        $relations = $this->getWithRelations($results);
        $rows = is_array($results) ? $results : iterator_to_array($results);

        $items = array_map(function (mixed $result) use ($relations, $primaryKey): AbstractModel {
            $row = $this->rowWithRelations((array) $result, $relations, $primaryKey);
            $itemModel = new $this->model($row, $this->model->getTableName());
            $itemModel->setWasRetrieved(true);

            return $itemModel;
        }, array_values($rows));

        return new Collection($items);
    }

    /**
     * Normalize the raw database result into an iterable value.
     *
     * Database drivers may return a non-iterable value; in that case the
     * result set is treated as empty.
     *
     * @param  mixed  $results  The raw driver result.
     * @return iterable<mixed> The iterable result set.
     */
    private function iterableResults(mixed $results): iterable
    {
        if (is_iterable($results)) {
            return $results;
        }

        return [];
    }

    /**
     * Merge the eager loaded relations into a raw result row.
     *
     * The merge only happens when the row carries a usable primary key,
     * because the eager loaded relations are indexed by that key.
     *
     * @param  array<array-key, mixed>                   $row         The raw result row.
     * @param  array<array-key, array<array-key, mixed>>  $relations   The eager loaded relations keyed by primary key.
     * @param  string                                    $primaryKey  The primary key column name.
     * @return array<array-key, mixed> The row with its eager loaded relations merged in.
     */
    private function rowWithRelations(array $row, array $relations, string $primaryKey): array
    {
        if (!isset($row[$primaryKey])) {
            return $row;
        }

        $key = $row[$primaryKey];

        if (is_int($key)) {
            return array_merge($row, $relations[$key] ?? []);
        }

        if (is_string($key)) {
            return array_merge($row, $relations[$key] ?? []);
        }

        return $row;
    }

    /**
     * Retrieve the first record matching the query constraints.
     *
     * @return AbstractModel|null First matching model instance or null if none found.
     */
    public function first(): ?AbstractModel
    {
        $results = $this->get();
        $first = $results->getAll()[0] ?? null;

        return $first instanceof AbstractModel ? $first : null;
    }

    /**
     * Retrieve the first record or throw an exception if none is found.
     *
     * This method behaves like "first()", but enforces the existence of a result.
     * It is commonly used when the absence of a record is considered an error
     * condition rather than a valid outcome.
     *
     * @return AbstractModel First matching model instance.
     * @throws ModelNotFoundException If no record matches the query constraints.
     */
    public function firstOrFail(): AbstractModel
    {
        $result = $this->first();

        if (!$result) {
            throw new ModelNotFoundException('No record found for the given query.');
        }

        return $result;
    }

    /**
     * Count all records matching the current query constraints.
     *
     * Executes a COUNT(*) query based on the generated SQL conditions.
     *
     * @return int Returns the total number of matching records.
     */
    public function count(): int
    {
        return (int)$this->db->getVar($this->generateQuery(true));
    }

    /**
     * Determine whether any records match the current query constraints.
     *
     * Executes a COUNT query internally and returns true if at least one record exists.
     *
     * @return bool True if at least one record exists, false otherwise.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Paginate the query results.
     *
     * Executes the query with LIMIT and OFFSET based on the current page,
     * and returns a paginator instance containing results and metadata.
     *
     * @param int $perPage Number of items per page.
     * @param string $queryPageKey Query string key used to determine current page number.
     * @return Paginator Paginated result set with metadata (total, per-page, current page).
     */
    public function paginate(mixed $perPage, string $queryPageKey = 'page'): Paginator
    {
        $perPage = (int)$perPage;
        $requestedPage = $_GET[$queryPageKey] ?? 1;
        $currentPage = max(1, (int)(is_scalar($requestedPage) ? $requestedPage : 1));
        $total       = $this->count();
        $items       = $this->offset(($currentPage - 1) * $perPage)
                            ->limit($perPage)
                            ->get();

        return new Paginator($items->getAll(), $total, $perPage, $currentPage);
    }
    #endregion

    #region Data Manipulation
    /**
     * Delete records matching the current query constraints.
     *
     * If the model supports soft deletes (trashed mode enabled), the record is updated
     * with a `deleted_at` timestamp instead of being physically removed.
     *
     * Otherwise, a hard delete is executed using the database driver.
     *
     * @param array<int, string>|null $whereFormat Optional format specification for the underlying delete operation.
     * @return bool|int Number of affected rows on success, or false on failure.
     */
    public function delete(?array $whereFormat = null): bool|int
    {
        if ($this->model->trashed()) {
            return $this->update(['deleted_at' => current_time('mysql')]);
        }

        return $this->db->delete($this->tableName, $this->conditionMap(), $whereFormat);
    }

    /**
     * Map the stored value conditions to a column => value array.
     *
     * Column comparisons and EXISTS conditions are not representable in the
     * array format understood by the database driver, so they are skipped.
     *
     * @return array<string, mixed> The conditions keyed by column.
     */
    private function conditionMap(): array
    {
        /** @var array<string, mixed> $where */
        $where = [];

        array_walk($this->whereArray, static function (array $item) use (&$where): void {
            if (isset($item['type'])) {
                return;
            }

            $where[$item['column']] = $item['value'];
        });

        return $where;
    }

    /**
     * Include soft-deleted records in the query results.
     *
     * Removes the automatic `deleted_at IS NULL` condition that is added
     * by the constructor when the model uses the soft delete trait, so
     * trashed records are no longer excluded.
     *
     * @return static Returns the current query builder instance for chaining.
     */
    public function withTrashed(): static
    {
        $this->whereArray = array_values(array_filter(
            $this->whereArray,
            static fn (array $item): bool => self::isNotSoftDeleteCondition($item)
        ));

        return $this;
    }

    /**
     * Check whether a WHERE condition is not the soft delete guard.
     *
     * @param  array<string, mixed>  $item  The condition to inspect.
     * @return bool True when the condition is not the `deleted_at IS NULL` guard.
     */
    private static function isNotSoftDeleteCondition(array $item): bool
    {
        if (($item['column'] ?? null) !== 'deleted_at') {
            return true;
        }

        if (($item['operator'] ?? null) !== 'IS') {
            return true;
        }

        return ($item['value'] ?? null) !== null;
    }

    /**
     * Restrict the query to soft-deleted records only.
     *
     * Includes trashed records and adds a `deleted_at IS NOT NULL` condition
     * so only records that have been soft-deleted are returned.
     *
     * @return static Returns the current query builder instance for chaining.
     */
    public function onlyTrashed(): static
    {
        $this->withTrashed();

        $this->whereArray[] = [
            'column'   => 'deleted_at',
            'value'    => null,
            'operator' => 'IS NOT',
        ];

        return $this;
    }

    /**
     * Physically remove records matching the current query constraints.
     *
     * Unlike delete(), this method always performs a hard delete even when
     * the model supports soft deletes. Call withTrashed() first when forcing
     * the deletion of records that were previously soft-deleted, so the
     * automatic `deleted_at IS NULL` condition does not narrow the query.
     *
     * @param array<int, string>|null $whereFormat Optional format specification for the underlying delete operation.
     * @return bool|int False on failure or the number of affected rows.
     */
    public function forceDelete(?array $whereFormat = null): bool|int
    {
        return $this->db->delete($this->tableName, $this->conditionMap(), $whereFormat);
    }

    /**
     * Update records matching the current query constraints.
     *
     * Builds a dynamic SQL UPDATE statement with bound values and applies all
     * accumulated WHERE, JOIN, ORDER BY, LIMIT and EXISTS conditions.
     *
     * @param array<string, mixed> $columnsValues Associative array of column => value pairs to update.
     * @return int|bool Number of affected rows on success or true/false depending on driver implementation.
     */
    public function update(array $columnsValues): bool|int
    {
        [$setClauses, $values] = $this->setClauses($columnsValues);

        $where = $this->resolveWhere();
        $conditions = array_filter([
            $this->resolveWhereExists(),
            $this->resolveWhereColumn(),
            implode(' ', $where['placeholders']),
        ]);

        $sql = "UPDATE {$this->tableName} SET " . implode(', ', $setClauses);

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
            $values = array_merge($values, $where['values']);
        }

        $sql = $this->appendOrderBy($sql);
        $sql = $this->appendLimit($sql);

        return $this->db->query($this->db->prepare($sql, ...$values));
    }

    /**
     * Build the SET clauses and the values they bind.
     *
     * @param  array<string, mixed>  $columnsValues  The columns and values to write.
     * @return array{0: array<int, string>, 1: array<int, mixed>} The SET clauses and their bound values.
     */
    private function setClauses(array $columnsValues): array
    {
        $setClauses = [];
        $values     = [];

        array_walk($columnsValues, static function (mixed $value, string $column) use (&$setClauses, &$values): void {
            if ($value === null) {
                $setClauses[] = "{$column} = NULL";

                return;
            }

            $setClauses[] = "{$column} = %s";
            $values[]     = $value;
        });

        return [$setClauses, $values];
    }

    /**
     * Append the LIMIT clause to the query.
     *
     * @param  string $sql The query built so far.
     * @return string The query including its LIMIT clause.
     */
    private function appendLimit(string $sql): string
    {
        if (!$this->limit) {
            return $sql;
        }

        return $sql . " LIMIT {$this->limit}";
    }
    #endregion

    #region SQL Generation
    /**
     * Generate the SQL query string for the current builder state.
     *
     * Can optionally generate a COUNT(*) query when $count is true.
     * Includes SELECT, JOIN, WHERE, GROUP BY, ORDER BY, LIMIT and OFFSET clauses.
     *
     * @param bool $count Whether to generate a COUNT(*) query instead of a SELECT query.
     * @return string The generated SQL query string.
     */
    protected function generateQuery(bool $count = false): string
    {
        $sql = $count ? "SELECT \count(*) FROM {$this->tableName}" : "SELECT {$this->select} FROM {$this->tableName}";

        $sql = $this->appendJoins($sql);
        $sql = $this->appendWhere($sql);
        $sql = $this->appendGroupBy($sql);
        $sql = $this->appendOrderBy($sql);

        return $this->appendLimits($sql);
    }

    /**
     * Append a condition, joining it with the previous one through its boolean connector.
     *
     * @param  array<int, string>  $conditions  The conditions collected so far.
     * @param  string  $condition  The condition to append.
     * @param  string  $method  The boolean connector to use when the condition is not first.
     * @return array<int, string> The conditions including the new one.
     */
    private function appendCondition(array $conditions, string $condition, string $method): array
    {
        if (empty($conditions)) {
            return [$condition];
        }

        return array_merge($conditions, ["{$method} {$condition}"]);
    }

    /**
     * Append every registered INNER JOIN clause to the query.
     *
     * @param  string $sql The query built so far.
     * @return string The query including its JOIN clauses.
     */
    private function appendJoins(string $sql): string
    {
        $joins = array_map(
            fn (array $join): string => " INNER JOIN {$join['table']} ON {$this->tableName}
            .{$join['local_key']} = {$join['table']}.{$join['foreign_key']}",
            $this->joinArray
        );

        return $sql . implode('', $joins);
    }

    /**
     * Append and bind the WHERE clause to the query.
     *
     * @param  string $sql The query built so far.
     * @return string The query including its bound WHERE clause.
     */
    private function appendWhere(string $sql): string
    {
        if (!$this->hasWhereConditions()) {
            return $sql;
        }

        $where = $this->resolveWhere();
        $whereSql = implode(' ', $where['placeholders']);
        $conditions = array_filter([
            $this->resolveWhereExists(),
            $this->resolveWhereColumn(),
            $whereSql,
        ]);
        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        return $this->db->prepare($sql, ...$where['values']);
    }

    /**
     * Append the GROUP BY clause to the query.
     *
     * @param  string $sql The query built so far.
     * @return string The query including its GROUP BY clause.
     */
    private function appendGroupBy(string $sql): string
    {
        if (empty($this->groupBy)) {
            return $sql;
        }

        return $sql . ' GROUP BY ' . implode(', ', $this->groupBy);
    }

    /**
     * Append the ORDER BY clause to the query.
     *
     * @param  string $sql The query built so far.
     * @return string The query including its ORDER BY clause.
     */
    private function appendOrderBy(string $sql): string
    {
        if (empty($this->orderBy)) {
            return $sql;
        }

        $orderBy = array_map(
            static fn (array $order): string => "{$order['column']} " . strtoupper($order['order']),
            $this->orderBy
        );

        return $sql . ' ORDER BY ' . implode(', ', $orderBy);
    }

    /**
     * Append the LIMIT and OFFSET clauses to the query.
     *
     * @param  string $sql The query built so far.
     * @return string The query including its LIMIT and OFFSET clauses.
     */
    private function appendLimits(string $sql): string
    {
        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Resolve all WHERE conditions into SQL placeholders and bound values.
     *
     * Supports:
     * - Nested conditions (closures)
     * - Raw SQL conditions
     * - IN clauses
     * - Standard column comparisons
     *
     * @return array{
     *     placeholders: array<int, string>,
     *     values: array<int, mixed>
     * } Structured query parts ready for SQL preparation.
     */
    public function resolveWhere(): array
    {
        /** @var array<int, string> $placeholders */
        $placeholders = [];
        /** @var array<int, mixed> $values */
        $values = [];

        array_walk($this->whereArray, function (array $where) use (&$placeholders, &$values): void {
            if (isset($where['type'])) {
                [$placeholders, $values] = $this->resolveTypedCondition($where, $placeholders, $values);

                return;
            }

            [$placeholders, $values] = $this->resolveValueCondition($where, $placeholders, $values);
        });

        return ['placeholders' => $placeholders, 'values' => $values];
    }

    /**
     * Resolve a nested or raw condition into placeholders and bound values.
     *
     * @param  array{type: 'Nested', callback: Closure, method?: string}|array{
     *     type: 'Raw',
     *     sql: string,
     *     bindings: array<int, mixed>,
     *     method?: string
     * }  $where  The stored condition.
     * @param  array<int, string>  $placeholders  The placeholders collected so far.
     * @param  array<int, mixed>  $values  The bound values collected so far.
     * @return array{0: array<int, string>, 1: array<int, mixed>} The placeholders and values collected so far.
     */
    private function resolveTypedCondition(array $where, array $placeholders, array $values): array
    {
        if ($where['type'] === 'Nested') {
            $nestedQuery = new self($this->model);
            $nestedQuery->whereArray = [];
            call_user_func($where['callback'], $nestedQuery);
            $nestedWhere = $nestedQuery->resolveWhere();

            if (empty($nestedWhere['placeholders'])) {
                return [$placeholders, $values];
            }

            $nestedSql = '(' . implode(' ', $nestedWhere['placeholders']) . ')';
            $method = $where['method'] ?? 'AND';
            $placeholders = $this->appendCondition($placeholders, $nestedSql, $method);

            return [$placeholders, array_merge($values, $nestedWhere['values'])];
        }

        $sql = $where['sql'];
        $method = $where['method'] ?? 'AND';
        $placeholders = $this->appendCondition($placeholders, "({$sql})", $method);

        if (empty($where['bindings'])) {
            return [$placeholders, $values];
        }

        return [$placeholders, array_merge($values, $where['bindings'])];
    }

    /**
     * Resolve a column comparison condition into placeholders and bound values.
     *
     * @param  array{
     *     column: string,
     *     value: mixed,
     *     operator: string,
     *     method?: string,
     *     table?: string
     * }  $where  The stored condition.
     * @param  array<int, string>  $placeholders  The placeholders collected so far.
     * @param  array<int, mixed>  $values  The bound values collected so far.
     * @return array{0: array<int, string>, 1: array<int, mixed>} The placeholders and values collected so far.
     */
    private function resolveValueCondition(array $where, array $placeholders, array $values): array
    {
        $tableName = $where['table'] ?? $this->tableName;
        $method = $where['method'] ?? 'AND';

        if ($where['value'] === null) {
            $operator = $where['operator'] === 'IS NOT' ? 'IS NOT' : 'IS';
            $placeholder = "{$tableName}.{$where['column']} {$operator} NULL";
            $placeholders = $this->appendCondition($placeholders, $placeholder, $method);

            return [$placeholders, $values];
        }

        $operator = $where['operator'];
        $bindings = $this->bindableValues($where['value']);
        $value = $operator === 'IN'
            ? '(' . implode(', ', array_fill(0, count($bindings), '%s')) . ')'
            : '%s';
        $placeholder = "{$tableName}.{$where['column']} {$operator} {$value}";
        $placeholders = $this->appendCondition($placeholders, $placeholder, $method);

        return [$placeholders, array_merge($values, $bindings)];
    }

    /**
     * Expand a condition value into the list of values it binds.
     *
     * Scalar values bind a single value, while an empty list still has to
     * produce one bound value, otherwise the generated placeholder would
     * carry no bindings at all.
     *
     * @param  mixed  $value  The raw condition value.
     * @return array<int, mixed> The values to bind, never an empty list.
     */
    private function bindableValues(mixed $value): array
    {
        if (!is_array($value)) {
            return [$value];
        }

        if (!empty($value)) {
            return array_values($value);
        }

        return [null];
    }

    /**
     * Check whether the builder carries at least one WHERE condition.
     *
     * @return bool True when a value or a column comparison is registered.
     */
    private function hasWhereConditions(): bool
    {
        if (!empty($this->whereArray)) {
            return true;
        }

        return !empty($this->whereColumnArray);
    }

    /**
     * Resolve column-to-column comparison conditions.
     *
     * Example: table.column_one = table.column_two
     *
     * @return string SQL fragment representing column comparison conditions.
     */
    public function resolveWhereColumn(): string
    {
        $placeholders = [];

        array_walk($this->whereColumnArray, function (array $where) use (&$placeholders): void {
            $operator    = $where['operator'] ?? '=';
            $placeholder = "{$this->tableName}.{$where['column_one']} {$operator} {$where['column_two']}";

            $placeholders = $this->appendCondition($placeholders, $placeholder, $where['method'] ?? 'AND');
        });

        return implode(' ', $placeholders);
    }

    /**
     * Resolve EXISTS / NOT EXISTS subquery conditions.
     *
     * Converts stored subqueries into EXISTS(...) or NOT EXISTS(...) SQL fragments.
     *
     * @return string SQL fragment containing EXISTS conditions.
     */
    public function resolveWhereExists(): string
    {
        $placeholders = [];
        array_walk($this->existsArray, function (array $where) use (&$placeholders): void {
            $not    = empty($where['not']) ? '' : 'NOT ';
            $exists = "{$not}EXISTS ({$where['sql']})";

            $placeholders = $this->appendCondition($placeholders, $exists, $where['method'] ?? 'AND');
        });

        return implode(' ', $placeholders);
    }
    #endregion

    #region Relations
    /**
     * Hydrate model results with their eager-loaded relations.
     *
     * Processes the configured `withArray` relations and executes batched queries
     * to attach related models to the given result set.
     *
     * @param iterable<mixed> $results Raw database result objects.
     * @return array<int|string, array<string, mixed>> Array of resolved relational data indexed by primary key.
     */
    public function getWithRelations(iterable $results): array
    {
        if (empty($this->withArray)) {
            return [];
        }

        $rows = array_values(array_map(
            static function (mixed $row): array {
                /** @var array<string, mixed> $row */
                return (array) $row;
            },
            is_array($results) ? $results : iterator_to_array($results)
        ));

        /** @var array<int|string, mixed> $ids */
        $ids = wp_list_pluck($rows, $this->model->getPrimaryKey());

        /** @var array<int|string, array<string, mixed>> $relations */
        $relations = [];

        array_walk($this->withArray, function (array $with) use (&$relations, $rows, $ids): void {
            $relations = $this->loadRelation($relations, $rows, $ids, $with);
        });

        return $relations;
    }

    /**
     * Load a single relation and attach it to the eager loaded data.
     *
     * @param  array<int|string, array<string, mixed>>  $relations  The data collected so far.
     * @param  array<int, array<string, mixed>>  $rows  The parent rows.
     * @param  array<int|string, mixed>  $ids  The parent primary keys.
     * @param  array{
     *     relation: string,
     *     table: string,
     *     foreign_key: string,
     *     local_key: string,
     *     model: class-string<AbstractModel>,
     *     relation_type: class-string<AbstractRelation>
     * }  $with  The relation configuration.
     * @return array<int|string, array<string, mixed>> The data collected so far, including this relation.
     */
    private function loadRelation(array $relations, array $rows, array $ids, array $with): array
    {
        $relation = $with['relation'];
        $relations = $this->openRelationSlots($relations, $ids, $relation, $with['relation_type']);

        /** @var array<int, mixed> $foreignIds */
        $foreignIds = wp_list_pluck($rows, $with['local_key']);

        /** @var AbstractModel $foreignModel */
        $foreignModel = new $with['model']();
        $relationResult = $foreignModel::whereIn($with['foreign_key'], $foreignIds)->get();

        if ($with['relation_type'] === BelongsTo::class) {
            return $this->attachInverse($relations, $rows, $relationResult, $with);
        }

        if ($with['relation_type'] === HasOne::class) {
            return $this->attachSingle($relations, $relationResult, $with);
        }

        return $this->attachMany($relations, $relationResult, $with);
    }

    /**
     * Reserve a slot for every usable parent key of a relation.
     *
     * @param  array<int|string, array<string, mixed>>  $relations  The data collected so far.
     * @param  array<int|string, mixed>  $ids  The parent primary keys.
     * @param  string  $relation  The relation name.
     * @param  string  $relationType  The relationship class name.
     * @return array<int|string, array<string, mixed>> The data including the reserved slots.
     */
    private function openRelationSlots(array $relations, array $ids, string $relation, string $relationType): array
    {
        array_walk($ids, function (mixed $id) use (&$relations, $relation, $relationType): void {
            $key = $this->relationKey($id);

            if ($key === null) {
                return;
            }

            $relations[$key][$relation] = $this->initialRelationData($relationType);
        });

        return $relations;
    }

    /**
     * Attach the related rows addressed through the parent foreign key.
     *
     * @param  array<int|string, array<string, mixed>>  $relations  The data collected so far.
     * @param  array<int, array<string, mixed>>  $rows  The parent rows.
     * @param  Collection<AbstractModel>  $relationResult  The related rows.
     * @param  array{
     *     relation: string,
     *     table: string,
     *     foreign_key: string,
     *     local_key: string,
     *     model: class-string<AbstractModel>,
     *     relation_type: class-string<AbstractRelation>
     * }  $with  The relation configuration.
     * @return array<int|string, array<string, mixed>> The data including the resolved relation.
     */
    private function attachInverse(array $relations, array $rows, Collection $relationResult, array $with): array
    {
        array_walk($rows, function (array $item) use (&$relations, $relationResult, $with): void {
            $foreignKey = $with['foreign_key'];
            $localKey   = $with['local_key'];
            $data = $relationResult->firstWhere($foreignKey, $item[$localKey]);
            $key  = $this->relationKey($item[$foreignKey]);

            if ($key === null) {
                return;
            }

            $relations[$key][$with['relation']] = $data;
        });

        return $relations;
    }

    /**
     * Attach a single related row to every parent that references it.
     *
     * @param  array<int|string, array<string, mixed>>  $relations  The data collected so far.
     * @param  Collection<AbstractModel>  $relationResult  The related rows.
     * @param  array{
     *     relation: string,
     *     table: string,
     *     foreign_key: string,
     *     local_key: string,
     *     model: class-string<AbstractModel>,
     *     relation_type: class-string<AbstractRelation>
     * }  $with  The relation configuration.
     * @return array<int|string, array<string, mixed>> The data including the resolved relation.
     */
    private function attachSingle(array $relations, Collection $relationResult, array $with): array
    {
        $foreignKey = $with['foreign_key'];
        $relation = $with['relation'];

        $related = iterator_to_array($relationResult);

        array_walk($related, function (AbstractModel $item) use (&$relations, $foreignKey, $relation): void {
            $key = $this->relationKey($item->$foreignKey);

            if ($key === null) {
                return;
            }

            $relations[$key][$relation] = $item;
        });

        return $relations;
    }

    /**
     * Append every related row to the collection of its parent.
     *
     * @param  array<int|string, array<string, mixed>>  $relations  The data collected so far.
     * @param  Collection<AbstractModel>  $relationResult  The related rows.
     * @param  array{
     *     relation: string,
     *     table: string,
     *     foreign_key: string,
     *     local_key: string,
     *     model: class-string<AbstractModel>,
     *     relation_type: class-string<AbstractRelation>
     * }  $with  The relation configuration.
     * @return array<int|string, array<string, mixed>> The data including the resolved relation.
     */
    private function attachMany(array $relations, Collection $relationResult, array $with): array
    {
        $foreignKey = $with['foreign_key'];
        $relation = $with['relation'];

        $related = iterator_to_array($relationResult);

        array_walk($related, function (AbstractModel $item) use (&$relations, $foreignKey, $relation): void {
            $key = $this->relationKey($item->$foreignKey);

            if ($key === null) {
                return;
            }

            /** @var Collection<AbstractModel>|null $collection */
            $collection = $relations[$key][$relation] ?? null;

            if (!$collection instanceof Collection) {
                return;
            }

            $collection->push($item);
        });

        return $relations;
    }

    /**
     * Normalize a value used to index the eager loaded relations map.
     *
     * @param  mixed  $key  The candidate key.
     * @return int|string|null The usable key, or null when the value cannot index the map.
     */
    private function relationKey(mixed $key): int|string|null
    {
        if (is_int($key)) {
            return $key;
        }

        if (is_string($key)) {
            return $key;
        }

        return null;
    }

    /**
     * Build the placeholder value of a pending eager loaded relation.
     *
     * Single valued relations are filled as soon as the related row is known,
     * while to-many relations start from an empty collection.
     *
     * @param  string  $relationType  The relationship class name.
     * @return Collection<AbstractModel>|null The placeholder, or null for single valued relations.
     */
    private function initialRelationData(string $relationType): ?Collection
    {
        if ($relationType === BelongsTo::class) {
            return null;
        }

        if ($relationType === HasOne::class) {
            return null;
        }

        return new Collection([]);
    }
    #endregion

    #region Getters
    /**
     * Retrieve the underlying model instance associated with this query builder.
     *
     * @return AbstractModel The model instance used by this query builder.
     */
    public function getModel(): AbstractModel
    {
        return $this->model;
    }
    #endregion
}
