<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database\Fixtures;

use Exception;
use Omega\Database\ORM\AbstractModel;
use Omega\Database\ORM\Relations\AbstractRelation;
use Omega\Database\ORM\Relations\BelongsTo;
use Omega\Database\ORM\Relations\HasMany;
use Omega\Database\ORM\Relations\HasOne;

/**
 * Model covering every relationship shape handled by the query builder.
 *
 * The fixture exposes one relation per supported return type plus the
 * deliberately broken shapes used to exercise the eager loading guards:
 * a relation pointing at a soft deleting model, a relation declared with
 * the abstract relation type, a relation without a return type and a
 * relation that always throws.
 *
 * @property mixed $id    Primary key resolved through the magic accessor.
 * @property mixed $title Title attribute handled by the mass assignment list.
 *
 * @category  Tests
 * @package   Database
 * @subpackage Fixtures
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class QuerySubject extends AbstractModel
{
    /**
     * Attributes accepted by fill().
     *
     * @var array<int, string>
     */
    protected array $fillable = ['title'];

    /**
     * Database table name.
     */
    protected string $table = 'subjects';

    /**
     * Inverse relationship pointing at a regular model.
     *
     * @return BelongsTo The configured belongs-to relationship instance.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PlainRecord::class);
    }

    /**
     * Inverse relationship pointing at a soft deleting model.
     *
     * @return BelongsTo The configured belongs-to relationship instance.
     */
    public function trashedParent(): BelongsTo
    {
        return $this->belongsTo(SoftDeletePost::class);
    }

    /**
     * One to one relationship pointing at a soft deleting model.
     *
     * @return HasOne The configured one-to-one relationship instance.
     */
    public function trashedAccessory(): HasOne
    {
        return $this->hasOne(SoftDeletePost::class);
    }

    /**
     * One to one relationship pointing at a regular model.
     *
     * @return HasOne The configured one-to-one relationship instance.
     */
    public function accessory(): HasOne
    {
        return $this->hasOne(AccessoredWidget::class);
    }

    /**
     * One to many relationship pointing at a regular model.
     *
     * @return HasMany The configured one-to-many relationship instance.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(TimestampedNote::class);
    }

    /**
     * Relationship declared with the abstract relation type.
     *
     * The eager loading configuration builder does not know this return
     * type, so the relation is silently dropped.
     *
     * @return AbstractRelation The untyped relationship instance.
     */
    public function unconfigured(): AbstractRelation
    {
        return new HasMany($this, TimestampedNote::class, 'subject_id', 'id');
    }

    /**
     * Relationship instance matching none of the supported shapes.
     *
     * Both the where-has and the eager loading guards have to ignore it.
     *
     * @return GenericRelation The custom relationship instance.
     */
    public function generic(): GenericRelation
    {
        return new GenericRelation($this, PlainRecord::class, 'subject_id', 'id');
    }

    /**
     * Relationship method declared without a return type.
     *
     * @return void
     */
    public function untyped()
    {
    }

    /**
     * Relationship method that always throws.
     *
     * @return HasMany Never returns.
     * @throws Exception Always thrown to exercise the eager loading guard.
     */
    public function exploding(): HasMany
    {
        throw new Exception('relation unavailable');
    }
}
