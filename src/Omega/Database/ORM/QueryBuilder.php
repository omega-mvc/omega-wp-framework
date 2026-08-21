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
use function array_merge;
use function call_user_func;
use function count;
use function current_time;
use function implode;
use function is_array;
use function is_callable;
use function is_string;
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
     *     operator: string,
     *     column_two: string,
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
     *     order: 'asc'|'desc'
     * }>
     */
    protected array $orderBy = [];

    /**
     * Selected columns for the query.
     *
     * Stored as a raw SQL select string (e.g. "*", "id, name, email").
     *
     * @var string
     */
    private string $select = '*';

    /**
     * Maximum number of records to return.
     *
     * Used to restrict result set size.
     *
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * Number of records to skip before returning results.
     *
     * Used for pagination and result slicing.
     *
     * @var int|null
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
            $this->whereArray[] = ['column' => 'deleted_at', 'value' => '!#####NULL#####!', 'operator' => 'IS'];
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
            foreach ($column as $col => $val) {
                $this->where($col, $val);
            }
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

        $where = [
            'column'   => $column,
            'value'    => $value ?? $operator,
            'operator' => isset($value) ? $operator : '='
        ];

        if ($method) {
            $where['method'] = $method;
        }

        if ($table) {
            $where['table'] = $table;
        }

        $this->whereArray[] = $where;

        return $this;
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
            foreach ($column as $col => $val) {
                $this->orWhere($col, $val);
            }
            return $this;
        }

        return $this->where($column, $operator, $value, 'OR');
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
        $this->where($column, 'IS', '!#####NULL#####!');

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
        $this->where($column, 'IS NOT', '!#####NULL#####!');

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

        if ($relation instanceof HasMany || $relation instanceof HasOne) {
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
            call_user_func($callback, $query);
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

        if (is_callable($callback)) {
            call_user_func($callback, $query);
        }

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
     * @param mixed $relation Relationship method name on the model.
     * @param mixed $column Column name in the related table.
     * @param mixed $valueOrOperator Comparison operator or value.
     * @param mixed|null $fieldValue Optional value when using a custom operator.
     * @param mixed $queryMethod Boolean operator used to join conditions (AND/OR).
     * @return static Returns the current query builder instance for chaining.
     * @throws ReflectionException Thrown when the relationship method cannot be resolved.
     */
    public function whereRelation(
        mixed $relation,
        mixed $column,
        mixed $valueOrOperator,
        mixed $fieldValue = null,
        mixed $queryMethod = 'AND'
    ): static {
        //TODO: Verify and refactor this
        $reflection = new ReflectionClass($this->model);
        $method     = $reflection->getMethod($relation);
        $returnType = $method->getReturnType();

        if ($returnType && $relation === $method->getName()) {
            if ($returnType instanceof ReflectionNamedType && $returnType->getName() === HasOne::class) {
                /**
                 * @var HasOne $hasOne
                 * TODO: Verify not implemented
                 */
                $hasOne       = $method->invoke($this->model);
                $relatedClass = $hasOne->getRelatedClass();
                $tableName    = $relatedClass::getFullTableName();

                $this->joinArray[] = [
                    'table'       => $tableName,
                    'foreign_key' => $this->model->getForeignKey() . '_id',
                    'local_key'   => 'id',
                ];

                //TODO: Replace by $this->where
                $this->whereArray[] = [
                    'method'   => $queryMethod,
                    'column'   => "{$column}",
                    'table'    => $tableName,
                    'value'    => $fieldValue ?? $valueOrOperator,
                    'operator' => isset($fieldValue) ? $valueOrOperator : '='
                ];

                if ($relatedClass::isTrashed()) {
                    $this->whereArray[] = [
                        'column'   => 'deleted_at',
                        'table'    => $tableName,
                        'value'    => '!#####NULL#####!',
                        'operator' => 'IS'
                    ];
                }
            }

            if ($returnType instanceof ReflectionNamedType && $returnType->getName() === BelongsTo::class) {
                /**
                 * @var BelongsTo $belongsTo
                 */
                $belongsTo    = $method->invoke($this->model);
                $relatedClass = $belongsTo->getRelatedClass();
                $tableName    = $relatedClass::getFullTableName();


                $this->joinArray[] = [
                    'table'       => $tableName,
                    'foreign_key' => $belongsTo->getLocalKey(),
                    'local_key'   => $belongsTo->getForeignKey()
                ];

                //TODO: Replace by $this->where
                $this->whereArray[] = [
                    'method'   => $queryMethod,
                    'column'   => "{$column}",
                    'table'    => $tableName,
                    'value'    => $fieldValue ?? $valueOrOperator,
                    'operator' => isset($fieldValue) ? $valueOrOperator : '='
                ];

                if ($relatedClass::isTrashed()) {
                    $this->whereArray[] = [
                        'column'   => 'deleted_at',
                        'table'    => $tableName,
                        'value'    => '!#####NULL#####!',
                        'operator' => 'IS'
                    ];
                }
            }
        }

        return $this;
    }

    /**
     * Add an OR WHERE condition based on a relationship constraint.
     *
     * This is a convenience wrapper around whereRelation() using OR as the boolean operator.
     *
     * @param mixed $relation Relationship method name on the model.
     * @param mixed $column Column name within the related table.
     * @param mixed $valueOrOperator Comparison value or operator.
     * @param mixed|null $fieldValue Optional comparison value when using a custom operator.
     * @return static Returns the current query builder instance for chaining.
     * @throws ReflectionException Thrown when the relationship cannot be resolved via reflection.
     */
    public function orWhereRelation(
        mixed $relation,
        mixed $column,
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
        foreach ($columns as $column) {
            $this->groupBy[] = $column;
        }
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param array<string, mixed> $column Column definition (may include nested structure depending on implementation).
     * @param string $order Sort direction ("asc" or "desc").
     * @return QueryBuilder Current query builder instance for chaining.
     */
    public function orderBy(array $column, string $order = 'asc'): QueryBuilder
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

        if (!is_array($relations)) {
            return $this;
        }

        foreach ($relations as $relation) {
            $this->addRelationToWith($relation);
        }

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

            $relationInstance = $method->invoke($this->model);
            $relatedClass     = $relationInstance->getRelatedClass();
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
     * @return array<string, mixed>|null Returns relation configuration array or null if unsupported.
     */
    private function buildRelationConfig(
        string $relationTypeName,
        string $relatedClass,
        string $relation
    ): ?array {
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
        return $this->where($this->model->primaryKey, $id)->first();
    }

    /**
     * Execute the query and return a collection of hydrated model instances.
     *
     * Also resolves eager-loaded relations if defined via `with()`.
     *
     * @return Collection<int, AbstractModel> Collection of hydrated model instances.
     */
    public function get(): Collection
    {
        $results = $this->db->getResults($this->generateQuery());
        $relations = $this->getWithRelations($results);
        $items = [];
        foreach ($results as $result) {
            $primaryKey = $this->model->getPrimaryKey();
            if (isset($result->$primaryKey)) {
                $relation = $relations[$result->$primaryKey] ?? [];
                $result = array_merge((array)$result, $relation);
            }
            $itemModel = new $this->model((array)$result, $this->model->getTableName());
            $itemModel->setWasRetrieved(true);

            $items[] = $itemModel;
        }

        return new Collection($items);
    }

    /**
     * Retrieve the first record matching the query constraints.
     *
     * @return AbstractModel|null First matching model instance or null if none found.
     */
    public function first(): ?AbstractModel
    {
        $results = $this->get();
        return $results[0] ?? null;
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
        $currentPage = (int)($_GET[$queryPageKey] ?? 1);
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
     * @param mixed|null $whereFormat Optional format specification for the underlying delete operation.
     * @return int|false Number of affected rows on success, or false on failure.
     */
    public function delete(mixed $whereFormat = null): int|false
    {
        if ($this->model->trashed()) {
            return $this->update(['deleted_at' => current_time('mysql')]);
        } else {
            $where = [];
            foreach ($this->whereArray as $item) {
                $where[$item['column']] = $item['value'];
            }
            return $this->db->delete($this->tableName, $where, $whereFormat);
        }
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
        $setClauses = [];
        $values     = [];

        foreach ($columnsValues as $column => $value) {
            $setClauses[] = "{$column} = %s";
            $values[]     = $value;
        }

        $setClause      = implode(', ', $setClauses);
        $whereExistsSql = $this->resolveWhereExists();
        $whereColumnSql = $this->resolveWhereColumn();
        $where          = $this->resolveWhere();
        $placeholders   = $where['placeholders'];
        $whereSql       = implode(' ', $placeholders);
        $conditions     = array_filter([$whereExistsSql, $whereColumnSql, $whereSql]);

        $sql = "UPDATE {$this->tableName} SET {$setClause}";

        if (!empty($conditions)) {
            $sql   .= ' WHERE ' . implode(' AND ', $conditions);
            $values = array_merge($values, $where['values']);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ';
            $orderBy = [];
            foreach ($this->orderBy as $order) {
                $orderBy[] = "{$order['column']} " . strtoupper($order['order']);
            }
            $sql .= implode(', ', $orderBy);
        }

        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }

        $preparedQuery = $this->db->prepare($sql, ...$values);

        return $this->db->query($preparedQuery);
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

        foreach ($this->joinArray as $join) {
            $sql .= " INNER JOIN {$join['table']} ON {$this->tableName}
            .{$join['local_key']} = {$join['table']}.{$join['foreign_key']}";
        }

        if (!empty($this->whereArray) || !empty($this->whereColumnArray)) {
            $sql .= ' WHERE ';
            $whereExistsSql = $this->resolveWhereExists();
            $whereColumnSql = $this->resolveWhereColumn();
            $where = $this->resolveWhere();
            $placeholders = $where['placeholders'];
            $values = $where['values'];
            $whereSql = implode(' ', $placeholders);
            $conditions = array_filter([$whereExistsSql, $whereColumnSql, $whereSql]);
            $sql .= implode(' AND ', $conditions);
            $sql = $this->db->prepare($sql, ...$values);
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ';
            $sql .= implode(', ', $this->groupBy);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ';
            $orderBy = [];
            foreach ($this->orderBy as $order) {
                $orderBy[] = "{$order['column']} " . strtoupper($order['order']);
            }
            $sql .= implode(', ', $orderBy);
        }

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
     *     placeholders: string[],
     *     values: array<int, mixed>
     * } Structured query parts ready for SQL preparation.
     */
    public function resolveWhere(): array
    {
        $placeholders = [];
        $values = [];

        foreach ($this->whereArray as $where) {
            if (isset($where['type']) && $where['type'] === 'Nested') {
                $nestedQuery = new self($this->model);
                $nestedQuery->whereArray = [];
                call_user_func($where['callback'], $nestedQuery);
                $nestedWhere = $nestedQuery->resolveWhere();

                if (!empty($nestedWhere['placeholders'])) {
                    $nestedSql = '(' . implode(' ', $nestedWhere['placeholders']) . ')';
                    $method = $where['method'] ?? 'AND';
                    $placeholders[] = empty($placeholders) ? $nestedSql : "{$method} {$nestedSql}";
                    $values = array_merge($values, $nestedWhere['values']);
                }
                continue;
            }

            if (isset($where['type']) && $where['type'] === 'Raw') {
                $sql = $where['sql'];
                $method = $where['method'] ?? 'AND';
                $placeholders[] = empty($placeholders) ? "({$sql})" : "{$method} ({$sql})";
                if (!empty($where['bindings'])) {
                    $values = array_merge($values, (array)$where['bindings']);
                }
                continue;
            }

            $operator = $where['operator'] ?? '=';
            $where['value'] = is_array($where['value']) && empty($where['value']) ? [null] : $where['value'];
            $value = $where['operator'] === 'IN'
                ? '(' . implode(', ', array_fill(0, count($where['value']), '%s')) . ')'
                : '%s';
            $table_name = $where['table'] ?? $this->tableName;
            $placeholder = "{$table_name}.{$where['column']} {$operator} {$value}";
            $method = $where['method'] ?? 'AND';
            $placeholders[] = empty($placeholders) ? $placeholder : "{$method} {$placeholder}";

            if (is_array($where['value'])) {
                $values = array_merge($values, $where['value']);
            } else {
                $values[] = $where['value'];
            }
        }

        return ['placeholders' => $placeholders, 'values' => $values];
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

        foreach ($this->whereColumnArray as $where) {
            $operator = $where['operator'] ?? '=';
            $column_two = $where['column_two'];
            $table_name = $this->tableName;
            $placeholder = "{$table_name}.{$where['column_one']} {$operator} {$column_two}";
            $method = $where['method'] ?? 'AND';
            $placeholders[] = empty($placeholders) ? $placeholder : "{$method} {$placeholder}";
        }

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
        foreach ($this->existsArray as $where) {
            $placeholder = $where['sql'];
            $method = $where['method'] ?? 'AND';
            $not = ! empty($where['not']);
            $exists = ( $not ? 'NOT ' : '' ) . "EXISTS ({$placeholder})";
            $placeholders[] = empty($placeholders) ? $exists : "{$method} {$exists}";
        }

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
     * @param array<int, object> $results Raw database result objects.
     * @return array<int, array<string, mixed>> Array of resolved relational data indexed by primary key.
     */
    public function getWithRelations(array $results): array
    {
        if (empty($this->withArray)) {
            return [];
        }

        $relations = [];

        $ids = wp_list_pluck($results, $this->model->getPrimaryKey());

        //TODO: optimize this
        foreach ($this->withArray as $with) {
            foreach ($ids as $id) {
                $initial_data = $with['relation_type'] !== BelongsTo::class && $with['relation_type'] !== HasOne::class
                    ? new Collection([])
                    : null;
                $relations[$id][$with['relation']] = $initial_data;
            }


            $foreignIds = wp_list_pluck($results, $with['local_key']);

            /** @var AbstractModel $foreignModel */
            $foreignModel = new $with['model']();
            $relationResult = $foreignModel::whereIn($with['foreign_key'], $foreignIds)->get();

            if ($with['relation_type'] === BelongsTo::class) {
                foreach ($results as $item) {
                    $data = $relationResult->firstWhere($with['foreign_key'], $item->{$with['local_key']});
                    $foreignKey = $with['foreign_key'];
                    $relations[$item->$foreignKey][$with['relation']] = $data;
                }
            } elseif ($with['relation_type'] === HasOne::class) {
                foreach ($relationResult as $item) {
                    $foreignKey = $with['foreign_key'];
                    $relations[$item->$foreignKey][$with['relation']] = $item;
                }
            } else {
                foreach ($relationResult as $item) {
                    $foreignKey = $with['foreign_key'];
                    if (isset($relations[$item->$foreignKey], $relations[$item->$foreignKey][$with['relation']])) {
                        $relations[$item->$foreignKey][$with['relation']]->push($item);
                    }
                }
            }
        }

        return $relations;
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
