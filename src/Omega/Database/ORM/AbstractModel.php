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

use ArrayAccess;
use Omega\Application\ApplicationFactory;
use Omega\Collection\Collection;
use Omega\Database\Database;
use Omega\Database\ORM\Casts\ArrayCast;
use Omega\Database\ORM\Casts\Attribute;
use Omega\Database\ORM\Casts\BooleanCast;
use Omega\Database\ORM\Casts\CastsAttributesInterface;
use Omega\Database\ORM\Casts\MoneyCast;
use Omega\Database\ORM\Relations\BelongsTo;
use Omega\Database\ORM\Relations\HasMany;
use Omega\Database\ORM\Relations\HasOne;
use Omega\Paginator\Paginator;
use ReflectionClass;
use ReflectionException;
use ReturnTypeWillChange;

use function array_key_exists;
use function array_merge;
use function call_user_func;
use function class_exists;
use function class_uses;
use function current_time;
use function get_called_class;
use function in_array;
use function is_string;
use function lcfirst;
use function method_exists;
use function preg_replace;
use function str_replace;
use function strtolower;
use function ucwords;

/**
 * Base active record implementation for the Omega Eloquent ORM layer.
 *
 * This abstract model provides a lightweight ORM, built specifically for the
 * Omega framework and the WordPress ecosystem.
 *
 * It offers:
 * - dynamic attribute access
 * - automatic attribute casting
 * - mutators and accessors
 * - relationship handling
 * - query builder integration
 * - model persistence
 * - timestamp management
 * - array serialization
 * - mass assignment support
 * - soft delete detection
 *
 * Models extending this class automatically gain fluent database querying
 * capabilities through the QueryBuilder and relationship system.
 *
 * The class also implements ArrayAccess, allowing model attributes to be
 * accessed using array syntax in addition to object properties.
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
abstract class AbstractModel implements ArrayAccess
{
	#region Properties
    /** @var array<class-string, AbstractModel> Cached singleton-like model instances indexed by class name. */
    private static array $instances = [];

    /** @var string Primary key column name used by the model. */
    protected string $primaryKey = 'id';

    /** @var array<int, string> List of mass assignable attributes. If empty, all attributes are considered fillable. */
    protected array $fillable = [];

    /** @var string Optional custom table prefix appended after the WordPress prefix. */
    protected string $prefix = '';

    /** @var string Fully qualified database table name associated with the model. */
    protected string $table;

    /** @var string Default foreign key name derived from the model class. */
    protected string $foreignKey;

    /** @var array<string, mixed> Raw model attribute storage. */
    protected array $data = [];

    /** @var bool Indicates whether the model was retrieved from the database. */
    private bool $wasRetrieved = false;

    /** @var bool Determines whether automatic timestamp management is enabled. */
    public bool $timestamps = false;

    /** @var array<string, mixed> Attribute cast definitions indexed by attribute name. */
    protected array $casts = [];

    /** @var array<string, mixed> Tracks modified attributes pending database synchronization. */
    private array $updateData = [];

    /** @var Database Database manager instance used by the model. */
    protected mixed $db;
	#endregion

	#region Lifecycle
    /**
     * Retrieve the shared model instance for the current model class.
     *
     * This method maintains an internal cache of model instances indexed
     * by class name and returns the existing instance when available.
     *
     * The instance is primarily used internally to bootstrap query builders
     * and perform static ORM operations.
     *
     * @return AbstractModel The shared model instance for the current class.
     */
    public static function getInstance(): AbstractModel
    {
        $class = get_called_class();

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class([]);
        }

        return self::$instances[$class];
    }

    /**
     * Create a new model instance.
     *
     * Initializes the model database connection, resolves the associated
     * table name, generates the default foreign key name, and hydrates
     * model attributes with automatic casting support.
     *
     * @param array<string, mixed> $data Optional initial model attributes.
     * @param string|null $table Optional custom database table name.
     * @return void
     * @throws ReflectionException Thrown when model metadata reflection fails.
     */
    public function __construct(array $data = [], ?string $table = null)
    {
        $this->db         = ApplicationFactory::app('database');
        $this->table      = $table ?? self::getFullTableName();
        $this->foreignKey = $this->modelToForeign(get_called_class());
        $this->data       = $data;

        foreach ($data as $key => $value) {
            $this->data[$key] = $this->getAttributeValue($key, $value);
        }
    }
	#endregion

	#region Table Metadata
    /**
     * Resolve the fully qualified database table name for the model.
     *
     * If the model defines a custom table property, that value is used.
     * Otherwise, the table name is automatically generated from the
     * model class name using snake_case pluralization conventions.
     *
     * The WordPress table prefix and optional custom prefix are
     * automatically applied.
     *
     * @return string The fully qualified database table name.
     * @throws ReflectionException Thrown when model reflection metadata cannot be resolved.
     */
    public static function getFullTableName(): string
    {
        $class = get_called_class();
        $defaultTableName = static::getDefaultPropertyValue($class, 'table');

        if (empty($defaultTableName)) {
            return Database::getTableName(
                self::modelToTable(get_called_class()),
                self::getPrefix()
            );
        } else {
            return Database::getTableName(
                $defaultTableName,
                self::getPrefix()
            );
        }
    }

    /**
     * Convert a model class name into its default database table name.
     *
     * The generated table name uses snake_case formatting and a pluralized
     * suffix based on the model short class name.
     *
     * Example:
     * UserProfile => user_profiles
     *
     * @param object|class-string $model The model class name or model instance.
     * @return string The generated database table name.
     * @throws ReflectionException Thrown when model reflection metadata cannot be resolved.
     */
    public static function modelToTable(object|string $model): string
    {
        $reflect              = new ReflectionClass($model);
        $tableNameUnderscored = preg_replace('/(?<!^)([A-Z])/', '_$1', $reflect->getShortName());

        return strtolower($tableNameUnderscored) . 's';
    }

    /**
     * Generate the base foreign key name for a model.
     *
     * The generated value is based on the model short class name
     * converted to snake_case format.
     *
     * Example:
     * UserProfile => user_profile
     *
     * @param object|class-string $model The model class name or model instance.
     * @return string The generated foreign key base name.
     * @throws ReflectionException Thrown when model reflection metadata cannot be resolved.
     */
    private function modelToForeign(object|string $model): string
    {
        $reflect              = new ReflectionClass($model);
        $tableNameUnderscored = preg_replace('/(?<!^)([A-Z])/', '_$1', $reflect->getShortName());

        return strtolower($tableNameUnderscored);
    }

    /**
     * Retrieve the model foreign key base name.
     *
     * @return string The model foreign key base name.
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Retrieve the default static foreign key column name for the model.
     *
     * The generated foreign key is based on the model short class name
     * converted to snake_case format with the "_id" suffix appended.
     *
     * Example:
     * UserProfile => user_profile_id
     *
     * @return string The generated foreign key column name.
     */
    public static function getForeignKeyStatic(): string
    {
        $reflect              = new ReflectionClass(get_called_class());
        $tableNameUnderscored = preg_replace('/(?<!^)([A-Z])/', '_$1', $reflect->getShortName());

        return strtolower($tableNameUnderscored) . '_id';
    }

	/**
	 * Retrieve the fully qualified database table name.
	 *
	 * @return string The model table name including prefixes.
	 */
	public function getTableName(): string
	{
		return $this->table;
	}

	/**
	 * Retrieve the model primary key column name.
	 *
	 * @return string The primary key column name.
	 */
	public function getPrimaryKey(): string
	{
		return $this->primaryKey;
	}

	/**
	 * Retrieve the model table name statically.
	 *
	 * @return string The fully qualified model table name.
	 */
	public static function getTable(): string
	{
		$instance = self::getInstance();

		return $instance->table;
	}

	/**
	 * Retrieve the custom table prefix defined by the model.
	 *
	 * @return string The configured model table prefix.
	 * @throws ReflectionException If the given class cannot be reflected.
	 */
	public static function getPrefix(): string
	{
		$class = get_called_class();

		return static::getDefaultPropertyValue($class, 'prefix', '');
	}

	/**
	 * Determine whether timestamp management is enabled for the model.
	 *
	 * When enabled, the model automatically manages the
	 * "created_at" and "updated_at" columns.
	 *
	 * @return bool True when timestamps are enabled, otherwise false.
	 * @throws ReflectionException If the given class cannot be reflected.
	 */
	public static function usesTimestamps(): bool
	{
		$class = get_called_class();

		return static::getDefaultPropertyValue($class, 'timestamps', false);
	}

	/**
	 * Get the default value of a class property using reflection.
	 *
	 * Inspects the given class and retrieves the declared default value
	 * for the specified property. If the property does not exist or has
	 * no declared default value, the provided fallback value is returned.
	 *
	 * @param class-string|object $className Class name or object instance to inspect.
	 * @param string $propertyName Name of the property whose default value should be retrieved.
	 * @param mixed|null $default Fallback value returned when the property is not defined.
	 * @return mixed The declared default property value or the provided fallback value.
	 * @throws ReflectionException If the given class cannot be reflected.
	 */
	public static function getDefaultPropertyValue(
		string|object $className,
		string $propertyName,
		mixed $default = null
	): mixed {
		$reflectionClass   = new ReflectionClass($className);
		$defaultProperties = $reflectionClass->getDefaultProperties();

		return array_key_exists($propertyName, $defaultProperties)
			? $defaultProperties[$propertyName]
			: $default;
	}
	#endregion

	#region Query Builder
    /**
     * Create a new query builder instance for the current model.
     *
     * This method acts as the primary ORM query entry point and
     * initializes a fresh QueryBuilder bound to the model instance.
     *
     * @return QueryBuilder A query builder instance for the model.
     */
    public static function query(): QueryBuilder
    {
        $instance = self::getInstance();

        return new QueryBuilder($instance);
    }

    /**
     * Create a query builder instance bound to the current model instance.
     *
     * @return QueryBuilder A query builder instance for the current model.
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    /**
     * Eager load one or more model relationships.
     *
     * This method initializes a query builder configured to preload
     * the specified relationships during query execution.
     *
     * @param string|array<int, string> $relationName Relationship name or list of relationships.
     * @return QueryBuilder A configured query builder instance.
     */
    public static function with(array|string $relationName): QueryBuilder
    {
        return self::query()->with($relationName);
    }

    /**
     * Retrieve a model by its primary key.
     *
     * @param int $id The model primary key value.
     * @return AbstractModel|null The retrieved model instance or null when not found.
     */
    public static function find(int $id): ?AbstractModel
    {
        return self::query()->find($id);
    }

    /**
     * Retrieve all records for the current model.
     *
     * @return Collection<int, AbstractModel> A collection containing all model records.
     */
    public static function all(): Collection
    {
        $instance = self::getInstance();
        $builder  = new QueryBuilder($instance);

        return $builder->get();
    }

    /**
     * Count the total number of records for the current model.
     *
     * @return int The total number of matching records.
     */
    public static function count(): int
    {
        $instance = self::getInstance();
        $builder  = new QueryBuilder($instance);

        return $builder->count();
    }

    /**
     * Add a WHERE NULL clause to the query.
     *
     * @param mixed $column The column name to check for NULL values.
     * @return QueryBuilder A configured query builder instance.
     */
    public static function whereNull(mixed $column): QueryBuilder
    {
        return self::query()->whereNull($column);
    }

    /**
     * Add a basic WHERE clause to the query.
     *
     * Supports both standard comparisons and shorthand query syntax.
     *
     * Examples:
     * - where('id', 1)
     * - where('age', '>', 18)
     *
     * @param mixed $column The column name or query condition.
     * @param mixed $operator Optional comparison operator.
     * @param mixed $value Optional comparison value.
     * @return QueryBuilder A configured query builder instance.
     */
    public static function where(
        mixed $column,
        mixed $operator = null,
        mixed $value = null
    ): QueryBuilder {
        $instance = self::getInstance();
        $builder  = new QueryBuilder($instance);

        $builder->where($column, $operator, $value);

        return $builder;
    }

    /**
     * Add a relationship existence condition to the query.
     *
     * This method filters results based on the existence of a related
     * model relationship and allows additional query constraints
     * through the provided callback.
     *
     * @param string $relation The relationship method name.
     * @param callable $callback Callback used to customize the relation query.
     * @return QueryBuilder The query builder instance for method chaining.
     * @throws ReflectionException Thrown when relation metadata cannot be resolved.
     */
    public static function whereHas(string $relation, callable $callback): QueryBuilder
    {
        $instance = self::getInstance();
        $builder  = new QueryBuilder($instance);

        $builder->whereHas($relation, $callback);

        return $builder;
    }

    /**
     * Specify the columns that should be selected by the query.
     *
     * @param string|array $columns Column name or list of columns to select.
     * @return QueryBuilder The query builder instance for method chaining.
     */
    public static function select(string|array $columns): QueryBuilder
    {
        $instance = self::getInstance();
        $builder  = new QueryBuilder($instance);

        $builder->select($columns);

        return $builder;
    }

    /**
     * Add a WHERE IN condition to the query.
     *
     * Filters results where the specified column value exists
     * within the provided list of values.
     *
     * @param string $column The database column name.
     * @param array $values List of accepted values.
     * @return QueryBuilder The query builder instance for method chaining.
     */
    public static function whereIn(string $column, array $values = []): QueryBuilder
    {
        $instance = self::getInstance();
        $builder  = new QueryBuilder($instance);

        $builder->whereIn($column, $values);

        return $builder;
    }

	/**
	 * Conditionally modify a query builder instance.
	 *
	 * The callback is only executed when the provided condition
	 * evaluates to a truthy value.
	 *
	 * @param mixed $condition The condition to evaluate.
	 * @param callable $callback The callback used to modify the query.
	 * @return QueryBuilder The query builder instance.
	 */
	public static function when(mixed $condition, callable $callback): QueryBuilder
	{
		if (isset($condition) && !empty($condition) && $condition !== false) {
			return $callback(self::query(), $condition);
		}

		return self::query();
	}

	/**
	 * Paginate the model query results.
	 *
	 * @param mixed $perPage The number of items per page.
	 * @return Paginator The paginator instance containing query results.
	 */
	public static function paginate(mixed $perPage): Paginator
	{
		$builder = self::query();

		return $builder->paginate($perPage);
	}
	#endregion

	#region Persistence
    /**
     * Create and persist a new model instance.
     *
     * Attribute mutators and casts are automatically applied
     * before the record is inserted into the database.
     *
     * If timestamps are enabled, the "created_at" and "updated_at"
     * columns are automatically populated.
     *
     * @param array<string, mixed> $columnsValues Column values to insert.
     * @return false|AbstractModel The newly created model instance or false on failure.
     * @throws ReflectionException Thrown when model reflection metadata cannot be resolved.
     */
    public static function create(array $columnsValues): false|AbstractModel
    {
        $instance = self::getInstance();

        $tableName = $instance->getTableName();

        foreach ($columnsValues as $key => $value) {
            $columnsValues[$key] = $instance->setAttributeValue($key, $value, $columnsValues);
        }

        if (self::usesTimestamps()) {
            $columnsValues['created_at'] = current_time('mysql');
            $columnsValues['updated_at'] = current_time('mysql');
        }

        $inserted = Database::insert($tableName, $columnsValues);

        if ($inserted) {
            $class    = get_called_class();
            $newClass = new $class(
                array_merge($columnsValues, ['id' => $inserted])
            );

            $newClass->setWasRetrieved(true);

            return $newClass;
        }

        return false;
    }

    /**
     * Update existing records matching the specified conditions.
     *
     * @param array<string, mixed> $columnsValues Column values to update.
     * @param array<string, mixed> $whereValues WHERE clause conditions.
     * @return bool|int False on failure or the number of affected rows.
     */
    public static function update(array $columnsValues, array $whereValues): bool|int
    {
        $instance = self::getInstance();

        return $instance->db->update($instance->table, $columnsValues, $whereValues);
    }

    /**
     * Update an existing record or create a new one if no match exists.
     *
     * The method first attempts to locate a record matching the
     * provided conditions. If found, the record is updated.
     * Otherwise, a new record is created.
     *
     * @param array<string, mixed> $whereValues Conditions used to find the record.
     * @param array<string, mixed> $columnsValues Column values used for update or creation.
     * @return bool|int|AbstractModel Updated row count, created model instance, or false on failure.
     * @throws ReflectionException Thrown when model reflection metadata cannot be resolved.
     */
    public static function updateOrCreate(array $whereValues, array $columnsValues): bool|int|AbstractModel
    {
        $instance = self::getInstance();
        $existing = $instance->where($whereValues)->first();

        if ($existing) {
            return $existing->update($columnsValues, $whereValues);
        } else {
            $data = array_merge($whereValues, $columnsValues);

            return self::create($data);
        }
    }

    /**
     * Fill the model with attributes for a future update operation.
     *
     * Only attributes defined in the fillable list are accepted,
     * unless no fillable restrictions are configured.
     *
     * The values are temporarily stored and persisted when save()
     * is called.
     *
     * @param array<string, mixed> $data Attribute values to assign.
     * @return void
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable) || empty($this->fillable)) {
                $this->updateData[$key] = $value;
            }
        }
    }

    /**
     * Persist the current model instance to the database.
     *
     * If the model was previously retrieved from the database,
     * an UPDATE query is executed using the modified attributes.
     *
     * Otherwise, a new database record is inserted.
     *
     * @return false|int False on failure or the inserted/affected row count.
     */
    public function save(): false|int
    {
        if ($this->wasRetrieved()) {
            $data         = $this->updateData;
            $id           = $this->data[$this->primaryKey];
            $queryBuilder = new QueryBuilder($this);
            $result       = $queryBuilder->where($this->primaryKey, $id)->update($data);

            if ($result) {
                $this->data = array_merge($this->data, $this->updateData);
            }

            return $result;
        } else {
            $result = $this->db->insert($this->table, $this->data);

            if ($result) {
                $this->data[$this->primaryKey] = $result;
            }

            return $result;
        }
    }

    /**
     * Delete the current model instance from the database.
     *
     * The model must originate from an existing database record
     * in order to be deleted.
     *
     * @return bool|int False on failure or the number of affected rows.
     */
    public function delete(): bool|int
    {
        if ($this->wasRetrieved()) {
            $data         = $this->data;
            $id           = $data[$this->primaryKey];
            $queryBuilder = new QueryBuilder($this);

            return $queryBuilder->where($this->primaryKey, $id)->delete();
        }

        return false;
    }

	/**
	 * Insert multiple records into the model table.
	 *
	 * @param array<int, array<string, mixed>> $columnsValues List of rows to insert.
	 * @return bool|int False on failure or the number of affected rows.
	 */
	public static function createMany(array $columnsValues): bool|int
	{
		$instance = self::getInstance();

		return $instance->db->insertMultiple($instance->table, $columnsValues);
	}
	#endregion

	#region Relationships
    /**
     * Determine whether a relationship has already been loaded.
     *
     * @param string $relation The relationship name to check.
     * @return bool True when the relationship is loaded, otherwise false.
     */
    public function relationLoaded(string $relation): bool
    {
        return isset($this->data[$relation]);
    }

	/**
	 * Define a one-to-one relationship.
	 *
	 * @param string $relatedClass The related model class name.
	 * @return HasOne The configured one-to-one relationship instance.
	 */
	public function hasOne(string $relatedClass): HasOne
	{
		return new HasOne($this, $relatedClass, "{$this->foreignKey}_id", "id");
	}

	/**
	 * Define an inverse one-to-one or many relationship.
	 *
	 * @param string $relatedClass The related model class name.
	 * @return BelongsTo The configured belongs-to relationship instance.
	 * @throws ReflectionException Thrown when model reflection metadata cannot be resolved.
	 */
	public function belongsTo(string $relatedClass): BelongsTo
	{
		$foreignKey = $this->modelToForeign($relatedClass);

		return new BelongsTo($this, $relatedClass, "{$foreignKey}_id", "id");
	}

	/**
	 * Define a one-to-many relationship.
	 *
	 * @param string $relatedClass The related model class name.
	 * @return HasMany The configured one-to-many relationship instance.
	 */
	public function hasMany(string $relatedClass): HasMany
	{
		return new HasMany($this, $relatedClass, "{$this->foreignKey}_id", "id");
	}
	#endregion

	#region Attribute Casting
    /**
     * Set a raw attribute value on the model instance.
     *
     * This method directly assigns the value without applying
     * casts or attribute mutators.
     *
     * @param string $key The attribute name.
     * @param mixed $value The attribute value.
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

	/**
	 * Resolve the accessor or mutator method name for an attribute.
	 *
	 * Converts snake_case attribute names into camelCase method names
	 * and checks whether the method exists on the model.
	 *
	 * @param string $key The attribute name.
	 * @return string|null The resolved method name or null if not found.
	 */
	protected function getAttributeMethod(string $key): ?string
	{
		$method = lcfirst(str_replace('_', '', ucwords($key, '_')));

		return method_exists($this, $method) ? $method : null;
	}

	/**
	 * Retrieve and transform an attribute value.
	 *
	 * This method applies:
	 * - attribute accessors
	 * - custom attribute objects
	 * - configured attribute casts
	 *
	 * Built-in primitive casts and custom cast classes are both supported.
	 *
	 * @param string $key The attribute name.
	 * @param mixed $value Optional raw attribute value.
	 * @return mixed The transformed attribute value.
	 */
	private function getAttributeValue(string $key, mixed $value = null): mixed
	{
		$attributeMethod = $this->getAttributeMethod($key);

		if ($attributeMethod && method_exists($this, $attributeMethod)) {
			$attribute = $this->$attributeMethod();

			if ($attribute instanceof Attribute && $attribute->get) {
				return call_user_func(
					$attribute->get,
					$value !== null
						? $value
						: ($this->data[$key] ?? null),
					$this->data
				);
			}
		}

		$casts = $this->casts();

		if (array_key_exists($key, $casts)) {
			$cast = $casts[$key];

			if (is_string($cast)) {
				switch (strtolower($cast)) {
					case 'boolean':
					case 'bool':
						$cast = BooleanCast::class;
						break;

					case 'array':
						$cast = ArrayCast::class;
						break;

					case 'money':
						$cast = MoneyCast::class;
						break;

					case 'int':
					case 'integer':
						return (int) $value;

					case 'real':
					case 'float':
					case 'double':
						return (float) $value;

					case 'string':
						return $value === null ? null : (string) $value;
				}

				if (class_exists($cast)) {
					$cast = new $cast();
				}
			}

			if ($cast instanceof CastsAttributesInterface) {
				return $cast->get($this, $key, $value, $this->data);
			}
		}

		return $value;
	}

	/**
	 * Transform and prepare an attribute value before persistence.
	 *
	 * This method applies:
	 * - attribute mutators
	 * - custom attribute objects
	 * - configured attribute casts
	 *
	 * Built-in primitive casts and custom cast classes are supported.
	 *
	 * @param string $key The attribute name.
	 * @param mixed $value The raw attribute value.
	 * @param array<string, mixed> $data Additional attribute data context.
	 * @return mixed The transformed attribute value.
	 */
	private function setAttributeValue(
		string $key,
		mixed $value,
		array $data = []
	): mixed {
		$attributeMethod = $this->getAttributeMethod($key);

		if ($attributeMethod && method_exists($this, $attributeMethod)) {
			$attribute = $this->$attributeMethod();

			if ($attribute instanceof Attribute && $attribute->set) {
				return call_user_func(
					$attribute->set,
					$value,
					array_merge($data, $this->data)
				);
			}
		}

		$casts = $this->casts();

		if (array_key_exists($key, $casts)) {
			$cast = $casts[$key];

			if (is_string($cast)) {
				switch (strtolower($cast)) {
					case 'boolean':
					case 'bool':
						$cast = BooleanCast::class;
						break;

					case 'array':
						$cast = ArrayCast::class;
						break;

					case 'money':
						$cast = MoneyCast::class;
						break;

					case 'int':
					case 'integer':
						return (int) $value;

					case 'real':
					case 'float':
					case 'double':
						return (float) $value;

					case 'string':
						return $value === null ? null : (string) $value;
				}

				if (class_exists($cast)) {
					$cast = new $cast();
				}
			}

			if ($cast instanceof CastsAttributesInterface) {
				return $cast->set($this, $key, $value, $this->data);
			}
		}

		return $value;
	}

	/**
	 * Retrieve the model attribute cast definitions.
	 *
	 * @return array<string, mixed> The configured attribute casts.
	 */
	protected function casts(): array
	{
		return $this->casts ?? [];
	}
	#endregion

	#region Model State
    /**
     * Retrieve the database manager instance associated with the model.
     *
     * @return Database The active database manager instance.
     */
    public function getDatabase(): Database
    {
        return $this->db;
    }

    /**
     * Determine whether the model uses soft deletes.
     *
     * @return bool True when the model uses the SoftDeletesTrait, otherwise false.
     */
    public function trashed(): bool
    {
        return in_array(SoftDeletesTrait::class, class_uses($this));
    }

    /**
     * Determine statically whether the model uses soft deletes.
     *
     * @return bool True when the model uses the SoftDeletesTrait, otherwise false.
     */
    public static function isTrashed(): bool
    {
        return in_array(SoftDeletesTrait::class, class_uses(get_called_class()));
    }

	/**
	 * Determine whether the model was retrieved from the database.
	 *
	 * @return bool True when the model originates from the database.
	 */
	public function wasRetrieved(): bool
	{
		return $this->wasRetrieved;
	}

	/**
	 * Set the retrieved state of the model instance.
	 *
	 * @param bool $wasRetrieved The retrieved state value.
	 * @return void
	 */
	public function setWasRetrieved(bool $wasRetrieved): void
	{
		$this->wasRetrieved = $wasRetrieved;
	}
	#endregion

	#region Serialization
    /**
     * Convert the model instance into an array representation.
     *
     * Related models and collections are recursively converted
     * into arrays.
     *
     * @return array<string, mixed> The model data as an array.
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->data as $key => $value) {
            if ($value instanceof Collection) {
                $result[$key] = $value->map(function ($item) {
                    return $item instanceof AbstractModel
                        ? $item->toArray()
                        : $item;
                });
            } elseif ($value instanceof AbstractModel) {
                $result[$key] = $value->toArray();
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
	#endregion

	#region Helpers
    /**
     * Determine whether a given attribute exists in the model data.
     *
     * @param string $key The attribute name to check.
     * @return bool True when the attribute exists, otherwise false.
     */
    public function keyExists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
	#endregion

	#region Magic Methods
    /**
     * Dynamically retrieve a model attribute or relationship.
     *
     * Attribute accessors and casts are automatically applied
     * when resolving values.
     *
     * @param string $name The attribute or relationship name.
     * @return mixed The resolved attribute value.
     */
    public function __get(string $name)
    {
        if ($this->keyExists($name)) {
            return $this->data[$name];
        }

        $value = $this->getAttributeValue($name);

        if ($value !== null) {
            return $value;
        }

        return $this->$name;
    }

    /**
     * Dynamically assign a model attribute value.
     *
     * Attribute mutators and casts are automatically applied
     * before the value is stored.
     *
     * @param string $name The attribute name.
     * @param mixed $value The attribute value.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $value = $this->setAttributeValue($name, $value);

        if ($this->wasRetrieved()) {
            $this->updateData[$name] = $value;
        } else {
            $this->data[$name] = $value;
        }
    }

    /**
     * Determine whether a dynamic attribute exists.
     *
     * @param string $name The attribute name.
     * @return bool True when the attribute exists, otherwise false.
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
	#endregion

	#region ArrayAccess
    /**
     * Determine whether a value exists at the given array offset.
     *
     * @param mixed $offset The array offset to check.
     * @return bool True when the offset exists, otherwise false.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Retrieve a value from the model using array access syntax.
     *
     * Attribute accessors and casts are automatically applied
     * when resolving values.
     *
     * @param mixed $offset The array offset to retrieve.
     * @return mixed The resolved attribute value or null.
     */
    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        if ($this->keyExists($offset)) {
            return $this->data[$offset];
        }

        $value = $this->getAttributeValue($offset);

        if ($value !== null) {
            return $value;
        }

        return null;
    }

    /**
     * Assign a value using array access syntax.
     *
     * Attribute mutators and casts are automatically applied
     * before the value is stored.
     *
     * @param mixed $offset The target array offset.
     * @param mixed $value The value to assign.
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset !== null) {
            $value = $this->setAttributeValue($offset, $value);
        }

        if ($this->wasRetrieved()) {
            $this->updateData[$offset] = $value;
        } else {
            if ($offset === null) {
                $this->data[] = $value;
            } else {
                $this->data[$offset] = $value;
            }
        }
    }

    /**
     * Remove a value using array access syntax.
     *
     * @param mixed $offset The array offset to remove.
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
	#endregion
}
