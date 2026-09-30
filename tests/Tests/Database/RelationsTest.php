<?php

/**
 * Part of Omega - Tests\Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database;

use Omega\Database\ORM\Relations\AbstractHasOneOrMany;
use Omega\Database\ORM\Relations\AbstractRelation;
use Omega\Database\ORM\Relations\BelongsTo;
use Omega\Database\ORM\Relations\HasMany;
use Omega\Database\ORM\Relations\HasOne;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Database\Fixtures\PlainRecord;
use Tests\Database\Fixtures\SoftDeletePost;

/**
 * Covers the ORM relationship abstractions.
 *
 * @category  Tests
 * @package   Database
 * @subpackage ORM
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractRelation::class)]
#[CoversClass(AbstractHasOneOrMany::class)]
#[CoversClass(BelongsTo::class)]
#[CoversClass(HasMany::class)]
#[CoversClass(HasOne::class)]
final class RelationsTest extends DatabaseTestCase
{
    public function testRelationExposesItsConfiguration(): void
    {
        $parent = new SoftDeletePost(['id' => 7]);
        $model  = new PlainRecord(['id' => 2]);

        $relation = new HasMany($parent, $model, 'post_id', 'id');

        $this->assertInstanceOf(AbstractHasOneOrMany::class, $relation);
        $this->assertInstanceOf(AbstractRelation::class, $relation);
        $this->assertSame('post_id', $relation->getForeignKey());
        $this->assertSame('id', $relation->getLocalKey());
        $this->assertSame($model, $relation->getRelatedClass());
    }

    public function testRelationAcceptsAClassNameAsRelatedClass(): void
    {
        $relation = new HasOne(new SoftDeletePost(['id' => 7]), PlainRecord::class, 'post_id', 'id');

        $this->assertSame(PlainRecord::class, $relation->getRelatedClass());
    }

    public function testBelongsToIsARelation(): void
    {
        $relation = new BelongsTo(new SoftDeletePost(['id' => 7]), PlainRecord::class, 'post_id', 'id');

        $this->assertInstanceOf(AbstractRelation::class, $relation);
        $this->assertSame('post_id', $relation->getForeignKey());
    }

    public function testSaveStoresTheForeignKeyOfTheParentModel(): void
    {
        $parent   = new SoftDeletePost(['id' => 7]);
        $relation = new HasMany($parent, PlainRecord::class, 'post_id', 'id');
        $model    = new PlainRecord(['title' => 'Hello']);

        $saved = $relation->save($model);

        $this->assertSame($model, $saved);
        $this->assertSame([['wp_todos', ['title' => 'Hello', 'post_id' => 7]]], $this->wpdb()->inserts);
    }

    public function testCreatePersistsTheRelatedModelWithTheForeignKey(): void
    {
        $parent   = new SoftDeletePost(['id' => 9]);
        $relation = new HasOne($parent, PlainRecord::class, 'post_id', 'id');

        $created = $relation->create(['title' => 'Fresh']);

        $this->assertInstanceOf(PlainRecord::class, $created);
        $this->assertSame([['wp_todos', ['title' => 'Fresh', 'post_id' => 9]]], $this->wpdb()->inserts);
    }

    public function testDeleteRemovesTheRelatedRecords(): void
    {
        $parent   = new SoftDeletePost(['id' => 4]);
        $relation = new HasMany($parent, PlainRecord::class, 'post_id', 'id');

        $this->assertSame(1, $relation->delete());
        $this->assertSame('wp_todos', $this->wpdb()->deletes[0][0]);
        $this->assertSame(['post_id' => 4], $this->wpdb()->deletes[0][1]);
    }

    public function testDeleteReturnsFalseWhenTheQueryFails(): void
    {
        $parent = new SoftDeletePost(['id' => 4]);
        $relation = new HasMany($parent, PlainRecord::class, 'post_id', 'id');

        $this->wpdb()->failNext = true;

        $this->assertFalse($relation->delete());
    }
}
