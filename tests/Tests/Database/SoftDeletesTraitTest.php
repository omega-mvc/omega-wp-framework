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

namespace Tests\Database;

use Omega\Database\ORM\AbstractModel;
use Omega\Database\ORM\QueryBuilder;
use Omega\Database\ORM\SoftDeletesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\Database\Fixtures\PlainRecord;
use Tests\Database\Fixtures\SoftDeletePost;

use function end;

/**
 * Tests the soft delete trait and its query builder integration.
 *
 * @category  Tests
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversTrait(SoftDeletesTrait::class)]
#[CoversClass(QueryBuilder::class)]
#[CoversClass(AbstractModel::class)]
final class SoftDeletesTraitTest extends DatabaseTestCase
{
    /**
     * Test restore() rejects models that were not retrieved from the database.
     */
    public function testRestoreReturnsFalseWhenModelWasNotRetrieved(): void
    {
        $post = new SoftDeletePost(['id' => 9]);

        $this->assertFalse($post->restore());
    }

    /**
     * Test forceDelete() rejects models that were not retrieved from the database.
     */
    public function testForceDeleteReturnsFalseWhenModelWasNotRetrieved(): void
    {
        $post = new SoftDeletePost(['id' => 9]);

        $this->assertFalse($post->forceDelete());
    }

    /**
     * Test model delete() rejects models that were not retrieved from the database.
     */
    public function testDeleteReturnsFalseWhenModelWasNotRetrieved(): void
    {
        $post = new SoftDeletePost(['id' => 9]);

        $this->assertFalse($post->delete());
    }

    /**
     * Test delete() soft deletes records when the model uses the trait.
     */
    public function testDeleteUpdatesDeletedAtWhenModelUsesSoftDeletes(): void
    {
        $post = new SoftDeletePost(['id' => 3]);
        $post->setWasRetrieved(true);

        $this->assertSame(1, $post->delete());
        $this->assertStringContainsString(
            "UPDATE wp_posts SET deleted_at = '2026-01-01 00:00:00'",
            $this->lastQuery()
        );
    }

    /**
     * Test delete() physically removes records when the model has no trait.
     */
    public function testDeletePhysicallyRemovesRecordWithoutSoftDeletes(): void
    {
        $record = new PlainRecord(['id' => 5]);
        $record->setWasRetrieved(true);

        $this->assertSame(1, $record->delete());
        $this->assertSame([['wp_todos', ['id' => 5]]], $this->wpdb()->deletes);
    }

    /**
     * Test restore() clears the deleted_at column of a retrieved model.
     */
    public function testRestoreClearsDeletedAtColumn(): void
    {
        $post = new SoftDeletePost(['id' => 7]);
        $post->setWasRetrieved(true);

        $this->assertSame(1, $post->restore());

        $query = $this->lastQuery();
        $this->assertStringContainsString('SET deleted_at = NULL', $query);
        $this->assertStringContainsString('wp_posts.id = 7', $query);
        $this->assertStringNotContainsString('deleted_at IS', $query);
    }

    /**
     * Test forceDelete() physically removes a soft-deleted record.
     */
    public function testForceDeletePhysicallyRemovesSoftDeletedRecord(): void
    {
        $post = new SoftDeletePost(['id' => 7]);
        $post->setWasRetrieved(true);

        $this->assertSame(1, $post->forceDelete());
        $this->assertSame([['wp_posts', ['id' => 7]]], $this->wpdb()->deletes);
    }

    /**
     * Test the default query excludes soft-deleted records.
     */
    public function testDefaultQueryExcludesSoftDeletedRecords(): void
    {
        SoftDeletePost::query()->where('id', 1)->get();

        $this->assertStringContainsString(
            "deleted_at IS '!#####NULL#####!'",
            $this->lastQuery()
        );
    }

    /**
     * Test withTrashed() drops the soft delete exclusion clause.
     */
    public function testWithTrashedIncludesSoftDeletedRecords(): void
    {
        SoftDeletePost::withTrashed()->where('id', 1)->get();

        $query = $this->lastQuery();
        $this->assertStringContainsString('wp_posts.id = 1', $query);
        $this->assertStringNotContainsString('deleted_at', $query);
    }

    /**
     * Test onlyTrashed() scopes the query to soft-deleted records.
     */
    public function testOnlyTrashedRestrictsToSoftDeletedRecords(): void
    {
        SoftDeletePost::onlyTrashed()->where('id', 1)->get();

        $this->assertStringContainsString(
            "deleted_at IS NOT '!#####NULL#####!'",
            $this->lastQuery()
        );
    }

    /**
     * Test update() emits a literal NULL assignment for null values.
     */
    public function testUpdateSupportsNullColumnValues(): void
    {
        $post = new SoftDeletePost(['id' => 1]);

        (new QueryBuilder($post))->where('id', 1)->update(['deleted_at' => null]);

        $this->assertStringContainsString('SET deleted_at = NULL', $this->lastQuery());
    }

    /**
     * Return the most recent query recorded by the wpdb mock.
     *
     * @return string The last prepared or executed query.
     */
    private function lastQuery(): string
    {
        $queries = $this->wpdb()->queries;
        $this->assertNotEmpty($queries);

        $last = end($queries);
        $this->assertIsString($last);

        return $last;
    }
}
