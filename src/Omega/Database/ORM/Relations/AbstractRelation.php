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

/**
 * AbstractRelation
 *
 * Base abstraction for all ORM relationship types.
 *
 * This class defines the core structure shared by all relationships between
 * models, such as HasOne, HasMany, and BelongsTo.
 *
 * It encapsulates the concept of:
 * - a parent model (the owning context of the relation)
 * - a related model class (the target of the relation)
 * - a foreign key (used to match records across tables)
 * - a local key (used as the reference point on the parent model)
 *
 * Concrete implementations are responsible for defining how the relationship
 * is resolved and how query logic is applied.
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
abstract class AbstractRelation
{
	#region Lifecycle
    /**
     * AbstractRelation constructor.
     *
     * Initializes a relationship instance linking a parent model to a related model.
     *
     * @param AbstractModel $parent The parent model that owns the relationship.
     * @param AbstractModel|string $relatedClass The related model class or instance.
     * @param string $foreignKey The foreign key used in the related table.
     * @param string $localKey The local key used in the parent model.
     */
    public function __construct(
        protected AbstractModel $parent,
        protected AbstractModel|string $relatedClass,
        protected string $foreignKey,
        protected string $localKey
    ) {
    }
	#endregion

	#region Accessors
    /**
     * Get the foreign key used in the relationship.
     *
     * This key represents the column in the related model's table that
     * references the parent model.
     *
     * @return string Foreign key column name.
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key used in the relationship.
     *
     * This key represents the column in the parent model's table that
     * is used for matching related records.
     *
     * @return string Local key column name.
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**

     * Get the related model class.
     *
     * This may return either a fully qualified model class name or an
     * instantiated AbstractModel depending on the resolution context.
     *
     * @return AbstractModel|string Related model class or instance.
     */
    public function getRelatedClass(): AbstractModel|string
    {
        return $this->relatedClass;
    }
	#endregion
}
