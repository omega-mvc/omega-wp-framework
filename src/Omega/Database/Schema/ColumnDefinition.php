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

use Omega\Database\Exceptions\ColumnDefinitionException;

use function is_bool;

/**
 * ColumnDefinition
 *
 * Represents a database table column definition within the schema builder.
 *
 * This class stores metadata and modifiers used to generate SQL column
 * declarations, including type information, indexes, default values,
 * nullability, and positioning directives.
 *
 * ColumnDefinition instances are typically created through Blueprint helper
 * methods and configured fluently before schema execution.
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
class ColumnDefinition
{
    #region Properties
    /** @var bool Indicates whether the column accepts NULL values. */
    protected bool $nullable = false;

    /** @var string The column name. */
    protected string $name;

    /** @var string The database column type. */
    protected string $type;

    /** @var bool Indicates whether the column auto increments. */
    protected bool $autoIncrement = false;

    /** @var bool Indicates whether the numeric column is unsigned. */
    protected bool $unsigned = false;

    /** @var bool Indicates whether the column is a primary key. */
    protected bool $primary = false;

    /** @var bool Indicates whether the column has a unique index. */
    protected bool $unique = false;

    /** @var bool Indicates whether the column has a standard index. */
    protected bool $index = false;

    /** @var mixed The default value assigned to the column. */
    protected mixed $default = null;

    /** @var string|null The column after which this column should be placed. */
    protected ?string $after = null;
    #endregion

    #region Lifecycle
    /**
     * Create a new column definition instance.
     *
     * Initializes the column metadata and applies the provided
     * schema configuration options.
     *
     * @param array<string, mixed> $data Column configuration attributes.
     * @return void
     * @throws ColumnDefinitionException Thrown when the column type or name is missing.
     */
    public function __construct(array $data)
    {
        if (empty($data['type'])) {
            throw new ColumnDefinitionException('Column type is required');
        }

        if (empty($data['name'])) {
            throw new ColumnDefinitionException('Column name is required');
        }

        $this->autoIncrement = $data['autoIncrement'] ?? false;
        $this->unsigned = $data['unsigned'] ?? false;
        $this->type = $data['type'];
        $this->name = $data['name'];
    }
    #endregion

    #region Modifiers
    /**
     * Mark the column as nullable.
     *
     * @return static The current column definition instance.
     */
    public function nullable(): static
    {
        $this->nullable = true;

        return $this;
    }

    /**
     * Mark the column as unsigned.
     *
     * Typically used for numeric column types.
     *
     * @return static The current column definition instance.
     */
    public function unsigned(): static
    {
        $this->unsigned = true;

        return $this;
    }

    /**
     * Mark the column as a primary key.
     *
     * @return static The current column definition instance.
     */
    public function primary(): static
    {
        $this->primary = true;

        return $this;
    }

    /**
     * Mark the column as unique.
     *
     * A unique index will be generated for this column.
     *
     * @return static The current column definition instance.
     */
    public function unique(): static
    {
        $this->unique = true;

        return $this;
    }

    /**
     * Mark the column as indexed.
     *
     * A standard index will be generated for this column.
     *
     * @return static The current column definition instance.
     */
    public function index(): static
    {
        $this->index = true;

        return $this;
    }

    /**
     * Set the default value for the column.
     *
     * @param mixed $value Default value assigned to the column.
     * @return static The current column definition instance.
     */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }
    #endregion

    #region Positioning
    /**
     * Specify the column position within the table.
     *
     * The column will be placed after the specified existing column
     * when generating ALTER TABLE statements.
     *
     * @param string $column Name of the reference column.
     * @return static The current column definition instance.
     */
    public function after(string $column): static
    {
        $this->after = $column;

        return $this;
    }

    /**
     * Get the column positioning reference.
     *
     * @return string|null The column name used for positioning, or null if not set.
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }
    #endregion

    #region State
    /**
     * Determine whether the column accepts NULL values.
     *
     * @return bool True if the column is nullable, false otherwise.
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Determine whether the column is unsigned.
     *
     * @return bool True if the column is unsigned, false otherwise.
     */
    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    /**
     * Determine whether the column auto increments.
     *
     * @return bool True if the column is auto incrementing, false otherwise.
     */
    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    /**
     * Determine whether the column is a primary key.
     *
     * @return bool True if the column is marked as primary, false otherwise.
     */
    public function isPrimary(): bool
    {
        return $this->primary;
    }

    /**
     * Determine whether the column has a unique index.
     *
     * @return bool True if the column is unique, false otherwise.
     */
    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * Determine whether the column has a standard index.
     *
     * @return bool True if the column is indexed, false otherwise.
     */
    public function isIndex(): bool
    {
        return $this->index;
    }
    #endregion

    #region Accessors
    /**
     * Get the database column type.
     *
     * @return string The configured column type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the column name.
     *
     * @return string The configured column name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the default column value.
     *
     * Boolean values are normalized to integer equivalents
     * for database compatibility.
     *
     * @return mixed The normalized default column value.
     */
    public function getDefault(): mixed
    {
        if (is_bool($this->default)) {
            return $this->default ? 1 : 0;
        }

        return $this->default;
    }
    #endregion

    #region Serialization
    /**
     * Get all column definition attributes.
     *
     * Returns the normalized internal state of the column definition,
     * including modifiers and schema metadata.
     *
     * @return array<string, mixed> Column definition attributes.
     */
    public function getAttributes(): array
    {
        return [
            'type'          => $this->getType(),
            'name'          => $this->getName(),
            'nullable'      => $this->isNullable(),
            'autoIncrement' => $this->isAutoIncrement(),
            'unsigned'      => $this->isUnsigned(),
            'primary'       => $this->isPrimary(),
            'index'         => $this->isIndex(),
            'after'         => $this->getAfter(),
        ];
    }
    #endregion
}
