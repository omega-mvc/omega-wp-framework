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

use function md5;
use function sprintf;
use function strlen;
use function strtoupper;
use function substr;

/**
 * ForeignKeyDefinition
 *
 * Represents a foreign key constraint definition inside a Blueprint schema.
 *
 * This class is responsible for storing foreign key configuration data and
 * generating the final SQL fragment required to enforce referential integrity
 * between two database tables.
 *
 * It supports fluent configuration of referenced columns, target tables,
 * and deletion behavior.
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
class ForeignKeyDefinition
{
	#region Lifecycle
    /**
     * Create a new foreign key definition instance.
     *
     * Initializes the foreign key configuration container that will be used
     * to build the final SQL constraint definition during schema execution.
     *
     * @param Blueprint $blueprint The parent schema blueprint instance.
     * @param array<string, mixed> $attributes Initial foreign key attributes.
     * @return void
     */
    public function __construct(
        protected Blueprint $blueprint,
        protected array $attributes = []
    ) {
    }
	#endregion

	#region Configuration
    /**
     * Set the referenced column for the foreign key constraint.
     *
     * Defines which column in the target table is referenced by this foreign key.
     *
     * @param string $column Name of the referenced column.
     * @return static The current foreign key definition instance.
     */
    public function references(string $column): static
    {
        $this->attributes['references'] = $column;

        return $this;
    }

    /**
     * Set the target table for the foreign key constraint.
     *
     * Defines the table that contains the referenced column.
     *
     * @param string $table Name of the referenced table.
     * @return static The current foreign key definition instance.
     */
    public function on(string $table): static
    {
        $this->attributes['on'] = $table;

        return $this;
    }

    /**
     * Define the action to perform when the referenced record is deleted.
     *
     * Common actions include: CASCADE, RESTRICT, SET NULL, NO ACTION.
     *
     * @param string $action Delete action to apply on foreign key constraint.
     * @return static The current foreign key definition instance.
     */
    public function onDelete(string $action): static
    {
        $this->attributes['onDelete'] = $action;

        return $this;
    }
	#endregion

	#region Accessors
	/**
	 * Get the raw foreign key definition attributes.
	 *
	 * Returns the internal configuration used to build the SQL constraint.
	 *
	 * @return array<string, mixed> Foreign key attributes.
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}
	#endregion

	#region Compilation
    /**
     * Generate the SQL fragment for the foreign key constraint.
     *
     * Builds a MySQL-compatible FOREIGN KEY constraint string, including
     * constraint naming, referenced table, column mapping, and optional
     * ON DELETE behavior.
     *
     * @return string The SQL foreign key constraint definition.
     */
    public function getForeignKeySql(): string
    {
        global $wpdb;

        $column     = $this->attributes['name'] ?? null;
        $references = $this->attributes['references'] ?? null;
        $table      = $this->attributes['on'] ?? null;
        $onDelete   = $this->attributes['onDelete'] ?? null;

        if (!$column || !$references || !$table) {
            return '';
        }

        $constraintName = sprintf(
            '%s_%s_foreign',
            $wpdb->prefix . $this->blueprint->getTable(),
            $column
        );

        if (strlen($constraintName) > 64) {
            $hash = substr(md5($constraintName), 0, 8);
            $base = substr($constraintName, 0, 55);
            $constraintName = $base . '_' . $hash;
        }

        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s)',
            $constraintName,
            $column,
            $wpdb->prefix . $table,
            $references
        );

        if ($onDelete) {
            $sql .= ' ON DELETE ' . strtoupper($onDelete);
        }

        return $sql;
    }
	#endregion
}
