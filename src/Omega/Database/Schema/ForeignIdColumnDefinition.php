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

use function explode;

/**
 * ForeignIdColumnDefinition
 *
 * Specialized column definition used for foreign key columns.
 *
 * This class extends the base ColumnDefinition with helper methods
 * for defining foreign key constraints using a fluent API.
 *
 * It integrates directly with the owning Blueprint instance to
 * generate and register foreign key commands.
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
class ForeignIdColumnDefinition extends ColumnDefinition
{
    #region Properties
    /** @var Blueprint The blueprint instance associated with the column definition. */
    protected Blueprint $blueprint;
    #endregion

    #region Lifecycle
    /**
     * Create a new foreign ID column definition instance.
     *
     * Initializes the foreign key column metadata and stores
     * the associated blueprint reference.
     *
     * @param Blueprint $blueprint The parent blueprint instance.
     * @param array<string, mixed> $attributes Column definition attributes.
     * @return void
     * @throws ColumnDefinitionException Thrown when required column attributes are missing.
     */
    public function __construct(Blueprint $blueprint, array $attributes = [])
    {
        parent::__construct($attributes);

        $this->blueprint = $blueprint;
    }
    #endregion

    #region Foreign Key
    /**
     * Create a foreign key constraint using conventional table and column names.
     *
     * If no table name is provided, the related table name is automatically
     * inferred from the foreign key column name.
     *
     * @param string|null $table Related table name.
     * @param string|null $column Referenced column name.
     * @param string|null $indexName Optional foreign key constraint name.
     * @return ForeignKeyDefinition The generated foreign key definition instance.
     */
    public function constrained(
        ?string $table = null,
        ?string $column = null,
        ?string $indexName = null
    ): ForeignKeyDefinition {
        $table ??= $this->getTableByColumn($this->name);
        $column ??= 'id';

        return $this->references($column, $indexName)->on($table);
    }

    /**
     * Define the referenced column for the foreign key constraint.
     *
     * @param string $column Referenced column name.
     * @param string|null $indexName Optional foreign key constraint name.
     * @return ForeignKeyDefinition The generated foreign key definition instance.
     */
    public function references(string $column, ?string $indexName = null): ForeignKeyDefinition
    {
        return $this->blueprint->foreign($this->name, $indexName)->references($column);
    }
    #endregion

    #region Resolution
    /**
     * Infer the related table name from a foreign key column name.
     *
     * For example, "user_id" becomes "users".
     *
     * @param string $column Foreign key column name.
     * @return string The inferred table name.
     */
    public function getTableByColumn(string $column): string
    {
        $parts = explode('_', $column);

        /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
        return "{$parts[0]}s";
    }
    #endregion
}
