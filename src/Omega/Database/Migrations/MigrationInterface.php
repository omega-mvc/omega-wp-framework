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

namespace Omega\Database\Migrations;

/**
 * MigrationInterface
 *
 * Defines the contract for database migration classes.
 *
 * A migration represents a reversible change to the database schema.
 * Each migration must define an "up" method to apply changes and a "down"
 * method to revert them.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Migration
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
interface MigrationInterface
{
	#region Migration
    /**
     * Apply the migration.
     *
     * This method should contain all logic required to modify the database
     * schema or data structure (e.g., creating tables, adding columns, etc.).
     *
     * @return void
     */
    public function up(): void;

    /**
     * Revert the migration.
     *
     * This method should undo all changes applied in the "up" method,
     * restoring the previous database state when possible.
     *
     * @return void
     */
    public function down(): void;
	#endregion
}
