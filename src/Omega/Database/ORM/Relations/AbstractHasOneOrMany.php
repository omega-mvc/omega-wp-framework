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

namespace Omega\Database\ORM\Relations;

use Omega\Database\ORM\AbstractModel;
use ReflectionException;

/**
 * AbstractHasOneOrMany
 *
 * Base implementation for one-to-one and one-to-many relationships.
 *
 * This class extends AbstractRelation and provides the shared behavior
 * for relationships where the foreign key is stored on the related model
 * and points back to the parent model.
 *
 * It defines the core operations for managing related models:
 * - attaching existing models to a parent (save)
 * - creating new related records with automatic foreign key assignment
 * - deleting related records tied to a parent model
 *
 * This abstraction is used by both HasOne and HasMany relationships,
 * which differ only in cardinality, not in underlying behavior.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Eloquent\Relations
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
abstract class AbstractHasOneOrMany extends AbstractRelation
{
    #region Persistence
    /**
     * Attach an existing model instance to the parent model.
     *
     * This method sets the appropriate foreign key on the given model
     * so that it becomes associated with the current parent model,
     * then persists it to the database.
     *
     * @param AbstractModel $model The related model instance to attach.
     * @return AbstractModel|false The saved model instance on success, or false on failure.
     */
    public function save(AbstractModel $model): AbstractModel|false
    {
        $this->setForeignAttributesForCreate($model);

        return $model->save() ? $model : false;
    }

    /**
     * Create and persist a new related model instance.
     *
     * Automatically injects the foreign key value based on the parent model,
     * ensuring the newly created record is properly linked in the relationship.
     *
     * @param array<string, mixed> $attributes Attributes for the new model instance.
     * @return AbstractModel|false The created model instance on success, or false on failure.
     * @throws ReflectionException Thrown when the underlying model structure or relation
     *                             cannot be resolved via reflection during dynamic relationship handling.
     */
    public function create(array $attributes): AbstractModel|false
    {
        $attributes[$this->getForeignKey()] = $this->parent->{$this->getLocalKey()};

        return $this->relatedClass::create($attributes);
    }

    /**
     * Delete all related records associated with the parent model.
     *
     * This executes a delete operation on the related model using the
     * foreign key constraint that links it to the parent.
     *
     * @return int|false Number of deleted records, or false on failure.
     */
    public function delete(): false|int
    {
        return $this->relatedClass::where(
            $this->getForeignKey(),
            $this->parent->{$this->getLocalKey()}
        )->delete();
    }
    #endregion

    #region Helpers
    /**
     * Set foreign key attributes on a model before creation or saving.
     *
     * Ensures that the related model correctly references the parent model
     * by assigning the appropriate foreign key value.
     *
     * @param AbstractModel $model The model instance to modify.
     * @return void
     */
    protected function setForeignAttributesForCreate(AbstractModel $model): void
    {
        $localKey = $this->getLocalKey();
        $model->setAttribute($this->getForeignKey(), $this->parent->$localKey);
    }
    #endregion
}
