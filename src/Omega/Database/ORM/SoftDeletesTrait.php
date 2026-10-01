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

/**
 * Trait SoftDeletesTrait
 *
 * Provides soft delete functionality for Eloquent-like models.
 *
 * Instead of permanently removing records from the database, this trait
 * marks them as deleted by setting a timestamp in the `deleted_at` column.
 *
 * This allows records to be excluded from default queries while still
 * remaining physically present in the database for recovery or auditing purposes.
 *
 * Models using this trait are expected to have a nullable `deleted_at` column.
 * Query builders typically integrate this trait to automatically filter out
 * soft-deleted records unless explicitly requested.
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
trait SoftDeletesTrait
{
    /**
     * Restore a soft-deleted model record.
     *
     * Clears the `deleted_at` column so the record is included in default
     * queries again. Only models retrieved from the database can be restored.
     *
     * @return bool|int False when the model was not retrieved, otherwise the
     *                  number of affected rows returned by the update.
     */
    public function restore(): bool|int
    {
        if (!$this->wasRetrieved()) {
            return false;
        }

        $id = $this->data[$this->primaryKey];

        return (new QueryBuilder($this))
            ->withTrashed()
            ->where($this->getPrimaryKey(), $id)
            ->update(['deleted_at' => null]);
    }

    /**
     * Permanently remove the model record from the database.
     *
     * Bypasses soft deletes and physically removes the row. Only models
     * retrieved from the database can be force deleted.
     *
     * @return bool|int False when the model was not retrieved, otherwise the
     *                  number of affected rows returned by the delete.
     */
    public function forceDelete(): bool|int
    {
        if (!$this->wasRetrieved()) {
            return false;
        }

        $id = $this->data[$this->primaryKey];

        return (new QueryBuilder($this))
            ->withTrashed()
            ->where($this->getPrimaryKey(), $id)
            ->forceDelete();
    }

    /**
     * Start a query that includes soft-deleted records.
     *
     * @return QueryBuilder The query builder scoped to the calling model.
     */
    public static function withTrashed(): QueryBuilder
    {
        return static::query()->withTrashed();
    }

    /**
     * Start a query scoped to soft-deleted records only.
     *
     * @return QueryBuilder The query builder scoped to the calling model.
     */
    public static function onlyTrashed(): QueryBuilder
    {
        return static::query()->onlyTrashed();
    }
}
