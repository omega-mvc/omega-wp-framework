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

use Omega\Database\Exceptions\ModelNotFoundException;
use Omega\Database\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Database\DatabaseTestCase;
use Tests\Database\Fixtures\QuerySubject;
use Tests\Database\Fixtures\SoftDeletePost;

use function array_filter;
use function array_map;
use function count;
use function end;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function trim;

/**
 * QueryBuilder test.
 *
 * @category  Tests
 * @package   Database
 * @subpackage Tests
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderTest extends DatabaseTestCase
{
    #region Column selection
    /**
     * Test a single column selection is forwarded to the generated query.
     */
    public function testItSelectsASingleColumn(): void
    {
        QuerySubject::query()->select('id, title')->get();

        $this->assertStringContainsString('SELECT id, title FROM wp_subjects', $this->lastQuery());
    }

    /**
     * Test an array of columns is joined with commas.
     */
    public function testItJoinsSelectedColumns(): void
    {
        QuerySubject::query()->select(['id', 'title'])->get();

        $this->assertStringContainsString('SELECT id, title FROM wp_subjects', $this->lastQuery());
    }
    #endregion

    #region Where conditions
    /**
     * Test a key value array is expanded into several conditions.
     */
    public function testWhereBuildsConditionsFromAnArray(): void
    {
        QuerySubject::query()->where(['title' => 'a', 'id' => 2])->get();

        $this->assertStringContainsString(
            "WHERE wp_subjects.title = 'a' AND wp_subjects.id = 2",
            $this->lastQuery()
        );
    }

    /**
     * Test a closure is compiled into a parenthesized group of conditions.
     */
    public function testWhereAcceptsANestedClosure(): void
    {
        QuerySubject::query()
            ->where('id', 1)
            ->where(function (QueryBuilder $nested): void {
                $nested->where('title', 'a')->orWhere('title', 'b');
            })
            ->get();

        $this->assertStringContainsString(
            "WHERE wp_subjects.id = 1 AND (wp_subjects.title = 'a' OR wp_subjects.title = 'b')",
            $this->lastQuery()
        );
    }

    /**
     * Test an explicit operator is honoured when a value is given.
     */
    public function testWhereHonoursAnExplicitOperator(): void
    {
        QuerySubject::query()->where('id', '>=', 3)->get();

        $this->assertStringContainsString('WHERE wp_subjects.id >= 3', $this->lastQuery());
    }

    /**
     * Test a non scalar operator falls back to the equality operator.
     */
    public function testWhereFallsBackToEqualsForNonScalarOperators(): void
    {
        QuerySubject::query()->where('id', new stdClass(), 3)->get();

        $this->assertStringContainsString('WHERE wp_subjects.id = 3', $this->lastQuery());
    }

    /**
     * Test a missing value forces the equality operator.
     */
    public function testWhereFallsBackToEqualsWhenTheValueIsMissing(): void
    {
        QuerySubject::query()->where('title', 'LIKE')->get();

        $this->assertStringContainsString("WHERE wp_subjects.title = 'LIKE'", $this->lastQuery());
    }

    /**
     * Test a non scalar column is compiled to an empty column name.
     */
    public function testWhereIgnoresNonScalarColumns(): void
    {
        QuerySubject::query()->where(new stdClass(), '=', 'x')->get();

        $this->assertStringContainsString("WHERE wp_subjects. = 'x'", $this->lastQuery());
    }

    /**
     * Test the boolean method and the table name are applied to the condition.
     */
    public function testWhereAppliesTheBooleanMethodAndTable(): void
    {
        QuerySubject::query()->where('id', '=', 1)->where('done', '=', 0, 'OR', 'todos')->get();

        $this->assertStringContainsString(
            'WHERE wp_subjects.id = 1 OR todos.done = 0',
            $this->lastQuery()
        );
    }

    /**
     * Test orWhere prefixes its condition with the OR boolean.
     */
    public function testOrWhereUsesTheOrBoolean(): void
    {
        QuerySubject::query()->where('id', 1)->orWhere('title', 'x')->get();

        $this->assertStringContainsString(
            "WHERE wp_subjects.id = 1 OR wp_subjects.title = 'x'",
            $this->lastQuery()
        );
    }

    /**
     * Test orWhere expands a key value array into one condition per entry.
     */
    public function testOrWhereBuildsConditionsFromAnArray(): void
    {
        QuerySubject::query()->where('id', 1)->orWhere(['title' => 'x', 'body' => 'y'])->get();

        $this->assertStringContainsString(
            "OR wp_subjects.title = 'x' OR wp_subjects.body = 'y'",
            $this->lastQuery()
        );
    }

    /**
     * Test whereNull compiles the soft delete sentinel.
     */
    public function testWhereNullBuildsAnIsNullCondition(): void
    {
        QuerySubject::query()->whereNull('deleted_at')->get();

        $this->assertStringContainsString(
            "WHERE wp_subjects.deleted_at IS NULL",
            $this->lastQuery()
        );
    }

    /**
     * Test whereNotNull negates the soft delete sentinel.
     */
    public function testWhereNotNullBuildsAnIsNotNullCondition(): void
    {
        QuerySubject::query()->whereNotNull('deleted_at')->get();

        $this->assertStringContainsString(
            "WHERE wp_subjects.deleted_at IS NOT NULL",
            $this->lastQuery()
        );
    }

    /**
     * Test a raw condition is wrapped in parentheses.
     */
    public function testWhereRawInjectsTheGivenSql(): void
    {
        QuerySubject::query()->whereRaw('LENGTH(title) > 3')->get();

        $this->assertStringContainsString('WHERE (LENGTH(title) > 3)', $this->lastQuery());
    }

    /**
     * Test a raw condition binds its values and honours the boolean.
     */
    public function testWhereRawBindsValuesAndHonoursTheBoolean(): void
    {
        QuerySubject::query()
            ->whereRaw('title = %s', ['a'])
            ->whereRaw('id = %s', [2], 'OR')
            ->get();

        $this->assertStringContainsString("WHERE (title = 'a') OR (id = 2)", $this->lastQuery());
    }

    /**
     * Test orWhereRaw prefixes its condition with the OR boolean.
     */
    public function testOrWhereRawUsesTheOrBoolean(): void
    {
        QuerySubject::query()->whereRaw('a = 1')->orWhereRaw('b = %s', [2])->get();

        $this->assertStringContainsString('WHERE (a = 1) OR (b = 2)', $this->lastQuery());
    }

    /**
     * Test whereIn expands the value list into placeholders.
     */
    public function testWhereInExpandsTheValueList(): void
    {
        QuerySubject::query()->whereIn('id', [1, 2, 3])->get();

        $this->assertStringContainsString('WHERE wp_subjects.id IN (1, 2, 3)', $this->lastQuery());
    }

    /**
     * Test an empty value list still binds a single placeholder.
     */
    public function testWhereInWithoutValuesStillBindsOnePlaceholder(): void
    {
        QuerySubject::query()->whereIn('id')->get();

        $this->assertStringContainsString("WHERE wp_subjects.id IN ('')", $this->lastQuery());
    }

    /**
     * Test whereColumn compares two qualified columns.
     */
    public function testWhereColumnComparesTwoColumns(): void
    {
        QuerySubject::query()->whereColumn('created_at', '>', 'updated_at')->get();

        $this->assertStringContainsString('WHERE wp_subjects.created_at > updated_at', $this->lastQuery());
    }

    /**
     * Test whereColumn accepts a single column pair.
     */
    public function testWhereColumnAcceptsASingleColumnPair(): void
    {
        QuerySubject::query()->whereColumn('parent_id', 'id')->get();

        $this->assertStringContainsString('WHERE wp_subjects.parent_id = id', $this->lastQuery());
    }

    /**
     * Test whereColumn honours the boolean method.
     */
    public function testWhereColumnHonoursTheBooleanMethod(): void
    {
        QuerySubject::query()->whereColumn('a', '=', 'b')->whereColumn('c', '=', 'd', 'OR')->get();

        $this->assertStringContainsString('WHERE wp_subjects.a = b OR wp_subjects.c = d', $this->lastQuery());
    }
    #endregion

    #region Relation conditions
    /**
     * Test whereHas constrains a to many relation.
     */
    public function testWhereHasConstrainsAToManyRelation(): void
    {
        QuerySubject::query()->where('id', 1)->whereHas('notes')->get();

        $this->assertStringContainsString(
            'EXISTS (SELECT * FROM wp_lab_notes WHERE wp_lab_notes.query_subject_id = wp_subjects.id)',
            $this->lastQuery()
        );
    }

    /**
     * Test whereHas constrains a one to one relation.
     */
    public function testWhereHasConstrainsAOneToOneRelation(): void
    {
        QuerySubject::query()->where('id', 1)->whereHas('accessory')->get();

        $this->assertStringContainsString(
            'EXISTS (SELECT * FROM wp_widgets WHERE wp_widgets.query_subject_id = wp_subjects.id)',
            $this->lastQuery()
        );
    }

    /**
     * Test whereHas constrains an inverse relation.
     */
    public function testWhereHasConstrainsAnInverseRelation(): void
    {
        QuerySubject::query()->where('id', 1)->whereHas('parent')->get();

        $this->assertStringContainsString(
            'EXISTS (SELECT * FROM wp_todos WHERE wp_todos.id = wp_subjects.plain_record_id)',
            $this->lastQuery()
        );
    }

    /**
     * Test the optional callback narrows the related query.
     */
    public function testWhereHasAppliesTheCallbackToTheRelatedQuery(): void
    {
        QuerySubject::query()->where('id', 1)->whereHas(
            'notes',
            function (QueryBuilder $related): void {
                $related->where('body', 'hello');
            }
        )->get();

        $this->assertStringContainsString(
            'EXISTS (SELECT * FROM wp_lab_notes WHERE wp_lab_notes.query_subject_id = wp_subjects.id'
            . " AND wp_lab_notes.body = 'hello')",
            $this->lastQuery()
        );
    }

    /**
     * Test a relation outside the supported shapes adds no column condition.
     */
    public function testWhereHasIgnoresUnknownRelationShapes(): void
    {
        QuerySubject::query()->where('id', 1)->whereHas('generic')->get();

        $this->assertStringContainsString('EXISTS (SELECT * FROM wp_todos)', $this->lastQuery());
    }

    /**
     * Test whereDoesntHave builds a NOT EXISTS condition.
     */
    public function testWhereDoesntHaveBuildsANotExistsCondition(): void
    {
        QuerySubject::query()->where('id', 1)->whereDoesntHave(
            'notes',
            function (QueryBuilder $related): void {
                $related->where('body', 'x');
            }
        )->get();

        $this->assertStringContainsString(
            'NOT EXISTS (SELECT * FROM wp_lab_notes WHERE wp_lab_notes.query_subject_id = wp_subjects.id'
            . " AND wp_lab_notes.body = 'x')",
            $this->lastQuery()
        );
    }

    /**
     * Test whereDoesntHave supports inverse relations.
     */
    public function testWhereDoesntHaveSupportsInverseRelations(): void
    {
        QuerySubject::query()->where('id', 1)->whereDoesntHave(
            'parent',
            function (QueryBuilder $related): void {
                $related->where('done', 1);
            }
        )->get();

        $this->assertStringContainsString(
            'NOT EXISTS (SELECT * FROM wp_todos WHERE wp_todos.id = wp_subjects.plain_record_id',
            $this->lastQuery()
        );
    }

    /**
     * Test whereDoesntHave ignores relations outside the supported shapes.
     */
    public function testWhereDoesntHaveIgnoresUnknownRelationShapes(): void
    {
        QuerySubject::query()->where('id', 1)->whereDoesntHave(
            'generic',
            function (QueryBuilder $related): void {
                $related->where('done', 1);
            }
        )->get();

        $this->assertStringContainsString(
            'NOT EXISTS (SELECT * FROM wp_todos WHERE wp_todos.done = 1)',
            $this->lastQuery()
        );
    }

    /**
     * Test whereRelation joins a one to one relation.
     */
    public function testWhereRelationJoinsAOneToOneRelation(): void
    {
        QuerySubject::query()->whereRelation('accessory', 'label', 'x')->get();

        $query = $this->lastQuery();
        $this->assertStringContainsString('INNER JOIN wp_widgets', $query);
        $this->assertStringContainsString("WHERE wp_widgets.label = 'x'", $query);
        $this->assertStringNotContainsString('deleted_at', $query);
    }

    /**
     * Test whereRelation adds the soft delete guard of a trashed relation.
     */
    public function testWhereRelationAddsTheSoftDeleteGuard(): void
    {
        QuerySubject::query()->whereRelation('trashedAccessory', 'title', 'x')->get();

        $query = $this->lastQuery();
        $this->assertStringContainsString("WHERE wp_posts.title = 'x'", $query);
        $this->assertStringContainsString("AND wp_posts.deleted_at IS NULL", $query);
    }

    /**
     * Test whereRelation joins an inverse relation.
     */
    public function testWhereRelationJoinsAnInverseRelation(): void
    {
        QuerySubject::query()->whereRelation('parent', 'done', 1)->get();

        $query = $this->lastQuery();
        $this->assertStringContainsString('INNER JOIN wp_todos', $query);
        $this->assertStringContainsString('wp_subjects.plain_record_id = wp_todos.id', $query);
    }

    /**
     * Test whereRelation adds the soft delete guard of a trashed inverse relation.
     */
    public function testWhereRelationAddsTheSoftDeleteGuardOfAnInverseRelation(): void
    {
        QuerySubject::query()->whereRelation('trashedParent', 'title', 'x')->get();

        $this->assertStringContainsString("AND wp_posts.deleted_at IS NULL", $this->lastQuery());
    }

    /**
     * Test a custom operator is honoured when a dedicated value is given.
     */
    public function testWhereRelationHonoursACustomOperator(): void
    {
        QuerySubject::query()->whereRelation('accessory', 'label', '>', 'x')->get();

        $this->assertStringContainsString("WHERE wp_widgets.label > 'x'", $this->lastQuery());
    }

    /**
     * Test a non scalar operator falls back to the equality operator.
     */
    public function testWhereRelationFallsBackToEqualsForNonScalarOperators(): void
    {
        QuerySubject::query()->whereRelation('accessory', 'label', new stdClass(), 'x')->get();

        $this->assertStringContainsString("WHERE wp_widgets.label = 'x'", $this->lastQuery());
    }

    /**
     * Test a relation name that does not match the method is ignored.
     */
    public function testWhereRelationIgnoresAMismatchedRelationName(): void
    {
        QuerySubject::query()->whereRelation('PARENT', 'done', 1)->get();

        $this->assertStringNotContainsString('WHERE', $this->lastQuery());
    }

    /**
     * Test a relation declared without a return type is ignored.
     */
    public function testWhereRelationIgnoresRelationsWithoutAReturnType(): void
    {
        QuerySubject::query()->whereRelation('untyped', 'done', 1)->get();

        $this->assertStringNotContainsString('WHERE', $this->lastQuery());
    }

    /**
     * Test a relation declared with an abstract return type is ignored.
     */
    public function testWhereRelationIgnoresUnsupportedRelationTypes(): void
    {
        QuerySubject::query()->whereRelation('unconfigured', 'done', 1)->get();

        $this->assertStringNotContainsString('WHERE', $this->lastQuery());
    }

    /**
     * Test orWhereRelation prefixes its condition with the OR boolean.
     */
    public function testOrWhereRelationUsesTheOrBoolean(): void
    {
        QuerySubject::query()->whereRelation('accessory', 'label', 'x')->orWhereRelation('parent', 'done', 1)->get();

        $this->assertStringContainsString("OR wp_todos.done = 1", $this->lastQuery());
    }
    #endregion

    #region Query composition
    /**
     * Test an empty column list leaves the query untouched.
     */
    public function testGroupByWithoutColumnsIsIgnored(): void
    {
        QuerySubject::query()->groupBy()->get();

        $this->assertSame('SELECT * FROM wp_subjects', $this->lastQuery());
    }

    /**
     * Test an empty relation list registers nothing.
     */
    public function testWithWithoutRelationsIsIgnored(): void
    {
        QuerySubject::query()->with([])->get();

        $this->assertCount(1, $this->wpdb()->queries);
    }

    /**
     * Test an empty condition array registers nothing.
     */
    public function testWhereWithAnEmptyArrayRegistersNothing(): void
    {
        QuerySubject::query()->where([])->orWhere([])->get();

        $this->assertSame('SELECT * FROM wp_subjects', $this->lastQuery());
    }

    /**
     * Test an explicit operator is dropped when the value is missing.
     */
    public function testWhereDropsTheOperatorWhenTheValueIsMissing(): void
    {
        QuerySubject::query()->where('title', 'LIKE')->get();

        $this->assertStringContainsString("WHERE wp_subjects.title = 'LIKE'", $this->lastQuery());
    }

    /**
     * Test a hard delete without conditions removes every record.
     */
    public function testDeleteWithoutConditionsRemovesEveryRecord(): void
    {
        QuerySubject::query()->delete();
        QuerySubject::query()->forceDelete();

        $this->assertSame([['wp_subjects', []], ['wp_subjects', []]], $this->wpdb()->deletes);
    }

    /**
     * Test grouping, ordering, limit and offset are appended in order.
     */
    public function testItAppliesGroupByOrderByLimitAndOffset(): void
    {
        QuerySubject::query()->groupBy('title')->orderBy('id', 'desc')->limit(5)->offset(10)->get();

        $query = $this->lastQuery();
        $this->assertStringContainsString('GROUP BY title', $query);
        $this->assertStringContainsString('ORDER BY id DESC', $query);
        $this->assertStringContainsString('LIMIT 5', $query);
        $this->assertStringContainsString('OFFSET 10', $query);
    }

    /**
     * Test several grouped and ordered columns are joined.
     */
    public function testItJoinsGroupedAndOrderedColumns(): void
    {
        QuerySubject::query()->groupBy('title', 'id')->orderBy('title')->orderBy('id', 'desc')->get();

        $query = $this->lastQuery();
        $this->assertStringContainsString('GROUP BY title, id', $query);
        $this->assertStringContainsString('ORDER BY title ASC, id DESC', $query);
    }

    /**
     * Test the model bound to the builder is exposed.
     */
    public function testGetModelReturnsTheBoundModel(): void
    {
        $this->assertInstanceOf(QuerySubject::class, QuerySubject::query()->getModel());
    }

    /**
     * Test the raw resolvers are exposed to the caller.
     */
    public function testTheResolversAreExposed(): void
    {
        $builder = QuerySubject::query()->where('id', 1)->whereColumn('a', '=', 'b')->whereHas('notes');

        $this->assertSame(
            ['wp_subjects.id = %s'],
            $builder->resolveWhere()['placeholders']
        );
        $this->assertSame('wp_subjects.a = b', $builder->resolveWhereColumn());
        $this->assertStringContainsString('EXISTS (', $builder->resolveWhereExists());
    }

    /**
     * Test an empty builder resolves to empty fragments.
     */
    public function testTheResolversHandleAnEmptyBuilder(): void
    {
        $builder = QuerySubject::query();

        $this->assertSame([], $builder->resolveWhere()['placeholders']);
        $this->assertSame('', $builder->resolveWhereColumn());
        $this->assertSame('', $builder->resolveWhereExists());
        $this->assertSame([], $builder->getWithRelations([]));
    }
    #endregion

    #region Eager loading
    /**
     * Test a to many relation is attached to every matching row.
     */
    public function testItEagerLoadsAToManyRelation(): void
    {
        $subjects = [
            (object) ['id' => 1, 'query_subject_id' => 1],
            (object) ['id' => 2, 'query_subject_id' => 99],
            (object) ['id' => 3],
            (object) ['title' => 'no key'],
        ];
        $notes = [
            (object) ['id' => 10, 'query_subject_id' => 1],
            (object) ['id' => 11, 'query_subject_id' => 1],
            (object) ['id' => 12, 'query_subject_id' => 404],
            (object) ['id' => 13, 'query_subject_id' => 1.5],
        ];

        $this->wpdb()->resultsResolver = static function (string $query) use ($subjects, $notes): array {
            return str_contains($query, 'wp_lab_notes') ? $notes : $subjects;
        };

        $items = QuerySubject::query()->with('notes')->get();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $items->toArray();

        $this->assertCount(4, $rows);

        /** @var array<int, array<string, mixed>> $relationRows */
        $relationRows = $rows[0]['notes'];

        $this->assertCount(2, $relationRows);
        $this->assertSame(10, $relationRows[0]['id']);
        $this->assertSame([], $rows[1]['notes']);
        $this->assertSame([], $rows[2]['notes']);
        $this->assertArrayNotHasKey('notes', $rows[3]);
    }

    /**
     * Test a one to one relation is attached to its owner.
     */
    public function testItEagerLoadsAOneToOneRelation(): void
    {
        $subjects = [(object) ['id' => 1, 'query_subject_id' => 1]];
        $widgets  = [(object) ['id' => 5, 'query_subject_id' => 1], (object) ['id' => 6, 'query_subject_id' => 1.5]];

        $this->wpdb()->resultsResolver = static function (string $query) use ($subjects, $widgets): array {
            return str_contains($query, 'wp_widgets') ? $widgets : $subjects;
        };

        /** @var array<int, array<string, mixed>> $rows */
        $rows = QuerySubject::query()->with(['accessory'])->get()->toArray();

        $this->assertCount(1, $rows);

        /** @var array<string, mixed> $accessory */
        $accessory = $rows[0]['accessory'];

        $this->assertSame(5, $accessory['id']);
    }

    /**
     * Test an inverse relation is looked up by its local key.
     */
    public function testItEagerLoadsAnInverseRelation(): void
    {
        $subjects = [
            (object) ['id' => null, 'plain_record_id' => 1],
            (object) ['id' => 1, 'plain_record_id' => 1],
            (object) ['id' => 3, 'plain_record_id' => 999],
        ];
        $parents = [(object) ['id' => 1, 'title' => 'parent']];

        $this->wpdb()->resultsResolver = static function (string $query) use ($subjects, $parents): array {
            return str_contains($query, 'wp_todos') ? $parents : $subjects;
        };

        /** @var array<int, array<string, mixed>> $rows */
        $rows = QuerySubject::query()->with('parent')->get()->toArray();

        $this->assertCount(3, $rows);
        $this->assertArrayNotHasKey('parent', $rows[0]);

        /** @var array<string, mixed> $parent */
        $parent = $rows[1]['parent'];

        $this->assertSame('parent', $parent['title']);
        $this->assertNull($rows[2]['parent']);
    }

    /**
     * Test relations are grouped by string keys as well as integer ones.
     */
    public function testItEagerLoadsRelationsKeyedByStrings(): void
    {
        $subjects = [(object) ['id' => '1', 'query_subject_id' => '1']];
        $notes    = [(object) ['id' => 10, 'query_subject_id' => '1']];

        $this->wpdb()->resultsResolver = static function (string $query) use ($subjects, $notes): array {
            return str_contains($query, 'wp_lab_notes') ? $notes : $subjects;
        };

        /** @var array<int, array<string, mixed>> $rows */
        $rows = QuerySubject::query()->with('notes')->get()->toArray();

        $this->assertCount(1, $rows);

        /** @var array<int, array<string, mixed>> $relationRows */
        $relationRows = $rows[0]['notes'];

        $this->assertCount(1, $relationRows);
        $this->assertSame(10, $relationRows[0]['id']);
    }

    /**
     * Test an unusable relation name never registers a query.
     */
    public function testWithIgnoresUnusableRelationNames(): void
    {
        $items = QuerySubject::query()
            ->with(['', 'missingRelation', 'PARENT', 'untyped', 'unconfigured', 'generic', 'exploding'])
            ->get();

        $this->assertCount(0, $items);
        $this->assertCount(1, $this->wpdb()->queries);
    }
    #endregion

    #region Retrieval
    /**
     * Test a row is hydrated for every usable primary key shape.
     */
    public function testGetHydratesRowsWithAUsablePrimaryKey(): void
    {
        $this->wpdb()->results = [
            (object) ['id' => 1],
            (object) ['id' => 'two'],
            (object) ['id' => 3.5],
            (object) ['title' => 'no key'],
        ];

        $items = QuerySubject::query()->get();

        $this->assertCount(4, $items);
    }

    /**
     * Test a non iterable result set is handled as an empty one.
     */
    public function testGetTreatsANonIterableResultAsEmpty(): void
    {
        $this->wpdb()->resultsResolver = static function (string $query): mixed {
            return null;
        };

        $this->assertTrue(QuerySubject::query()->get()->isEmpty());
    }

    /**
     * Test find builds a primary key condition and returns the first row.
     */
    public function testFindReturnsTheMatchingRecord(): void
    {
        $this->wpdb()->results = [(object) ['id' => 4, 'title' => 'four']];

        $record = QuerySubject::query()->find(4);

        $this->assertInstanceOf(QuerySubject::class, $record);
        $this->assertStringContainsString('WHERE wp_subjects.id = 4', $this->lastQuery());
    }

    /**
     * Test find returns null when no row matches.
     */
    public function testFindReturnsNullWhenNothingMatches(): void
    {
        $this->assertNull(QuerySubject::query()->find(9));
    }

    /**
     * Test firstOrFail returns the first matching row.
     */
    public function testFirstOrFailReturnsTheFirstRecord(): void
    {
        $this->wpdb()->results = [(object) ['id' => 1]];

        $this->assertInstanceOf(QuerySubject::class, QuerySubject::query()->firstOrFail());
    }

    /**
     * Test firstOrFail throws when no row matches.
     */
    public function testFirstOrFailThrowsWhenNothingMatches(): void
    {
        $this->expectException(ModelNotFoundException::class);

        QuerySubject::query()->firstOrFail();
    }

    /**
     * Test count runs a count query and casts the scalar result.
     */
    public function testItCountsRecords(): void
    {
        $this->wpdb()->varValue = '7';

        $this->assertSame(7, QuerySubject::query()->count());
        $this->assertStringContainsString('SELECT \count(*) FROM wp_subjects', $this->lastQuery());
    }

    /**
     * Test exists reports whether at least one row matched.
     */
    public function testExistsReportsWhetherRecordsWereFound(): void
    {
        $this->wpdb()->varValue = '1';
        $this->assertTrue(QuerySubject::query()->exists());

        $this->wpdb()->varValue = '0';
        $this->assertFalse(QuerySubject::query()->exists());
    }

    /**
     * Test paginate reads the requested page from the query string.
     */
    public function testItPaginatesResults(): void
    {
        $_GET['page'] = 2;
        $this->wpdb()->varValue = '25';

        $paginator = QuerySubject::query()->paginate(10);

        $this->assertSame(25, $paginator->getAttributes()['total']);
        $this->assertSame(2, $paginator->getAttributes()['current_page']);
        $this->assertSame(3, $paginator->getAttributes()['last_page']);
        $this->assertStringContainsString('LIMIT 10 OFFSET 10', $this->lastQuery());
        unset($_GET['page']);
    }

    /**
     * Test a non numeric page request falls back to the first page.
     */
    public function testItPaginatesFromTheFirstPageOnAnUnusableRequest(): void
    {
        $_GET['p'] = 'abc';
        $this->wpdb()->varValue = '3';

        $this->assertSame(1, QuerySubject::query()->paginate(10, 'p')->getAttributes()['current_page']);
        unset($_GET['p']);
    }

    /**
     * Test a non scalar page request falls back to the first page.
     */
    public function testItPaginatesFromTheFirstPageOnANonScalarRequest(): void
    {
        $_GET['p'] = [];
        $this->wpdb()->varValue = '3';

        $this->assertSame(1, QuerySubject::query()->paginate(10, 'p')->getAttributes()['current_page']);
        unset($_GET['p']);
    }
    #endregion

    #region Data manipulation
    /**
     * Test delete removes the rows of a regular model.
     */
    public function testDeleteRemovesRecordsOnRegularModels(): void
    {
        QuerySubject::query()->where('id', 1)->delete();

        $this->assertSame([['wp_subjects', ['id' => 1]]], $this->wpdb()->deletes);
    }

    /**
     * Test delete ignores raw conditions when building the where format.
     */
    public function testDeleteIgnoresRawConditions(): void
    {
        QuerySubject::query()->whereRaw('1 = 1')->where('id', 1)->delete();

        $this->assertSame([['wp_subjects', ['id' => 1]]], $this->wpdb()->deletes);
    }

    /**
     * Test delete soft deletes the rows of a trashed model.
     */
    public function testDeleteSoftDeletesRecordsOnTrashedModels(): void
    {
        SoftDeletePost::query()->where('id', 1)->delete();

        $this->assertStringContainsString('UPDATE wp_posts SET deleted_at = ', $this->lastQuery());
    }

    /**
     * Test forceDelete always removes the rows.
     */
    public function testForceDeleteAlwaysRemovesRecords(): void
    {
        SoftDeletePost::query()->withTrashed()->where('id', 1)->forceDelete();

        $this->assertSame([['wp_posts', ['id' => 1]]], $this->wpdb()->deletes);
    }

    /**
     * Test forceDelete ignores raw conditions when building the where format.
     */
    public function testForceDeleteIgnoresRawConditions(): void
    {
        SoftDeletePost::query()->whereRaw('1 = 1')->where('id', 2)->forceDelete();

        $this->assertSame(
            [['wp_posts', ['deleted_at' => null, 'id' => 2]]],
            $this->wpdb()->deletes
        );
    }

    /**
     * Test update builds the set clause and reuses the where conditions.
     */
    public function testUpdateBuildsAnUpdateStatement(): void
    {
        QuerySubject::query()->where('id', 1)->update(['title' => 'x', 'body' => null]);

        $this->assertStringContainsString(
            "UPDATE wp_subjects SET title = 'x', body = NULL WHERE wp_subjects.id = 1",
            $this->lastQuery()
        );
    }

    /**
     * Test update without conditions omits the where clause.
     */
    public function testUpdateWithoutConditionsSkipsTheWhereClause(): void
    {
        QuerySubject::query()->update(['title' => 'x']);

        $this->assertStringNotContainsString('WHERE', $this->lastQuery());
    }

    /**
     * Test update appends the ordering and the limit.
     */
    public function testUpdateAppliesOrderByAndLimit(): void
    {
        QuerySubject::query()->orderBy('title')->limit(2)->update(['title' => 'x']);

        $query = $this->lastQuery();
        $this->assertStringContainsString('ORDER BY title ASC', $query);
        $this->assertStringContainsString('LIMIT 2', $query);
    }

    /**
     * Test update combines the column, raw and relation conditions.
     */
    public function testUpdateCombinesEveryConditionKind(): void
    {
        QuerySubject::query()
            ->whereColumn('a', '=', 'b')
            ->whereHas('notes')
            ->where('id', 1)
            ->update(['title' => 'x']);

        $query = $this->lastQuery();
        $this->assertStringContainsString('EXISTS (', $query);
        $this->assertStringContainsString('wp_subjects.a = b', $query);
        $this->assertStringContainsString('wp_subjects.id = 1', $query);
    }

    /**
     * Test withTrashed removes the automatic soft delete guard.
     */
    public function testWithTrashedRemovesTheSoftDeleteGuard(): void
    {
        SoftDeletePost::query()->withTrashed()->get();

        $this->assertStringNotContainsString('deleted_at', $this->lastQuery());
    }

    /**
     * Test withTrashed keeps every condition that is not the soft delete guard.
     */
    public function testWithTrashedKeepsEveryOtherCondition(): void
    {
        SoftDeletePost::query()
            ->where('title', 'a')
            ->where('deleted_at', 'IS NOT', null)
            ->where('deleted_at', 'IS', '2020-01-01')
            ->withTrashed()
            ->get();

        $query = $this->lastQuery();
        $this->assertStringContainsString("wp_posts.deleted_at IS NOT NULL", $query);
        $this->assertStringContainsString("wp_posts.deleted_at IS '2020-01-01'", $query);
    }

    /**
     * Test onlyTrashed keeps the trashed records.
     */
    public function testOnlyTrashedFiltersSoftDeletedRecords(): void
    {
        SoftDeletePost::query()->onlyTrashed()->get();

        $this->assertStringContainsString(
            "WHERE wp_posts.deleted_at IS NOT NULL",
            $this->lastQuery()
        );
    }
    #endregion

    #region Clause combinations
    /**
     * Test the select generator assembles every optional clause combination.
     *
     * Every join, condition, grouping, ordering, limit and offset shape is
     * combined with every other one, both for the row and for the count query.
     */
    public function testItAssemblesEveryOptionalSelectClause(): void
    {
        $this->wpdb()->resultsResolver = static fn (string $query): array => [];

        foreach ($this->joinShapes() as $joins) {
            foreach ($this->whereShapes() as $conditions) {
                foreach ([false, true] as $grouped) {
                    foreach ([0, 1, 2] as $ordering) {
                        foreach ([0, 1] as $limited) {
                            foreach ([0, 1] as $offset) {
                                $this->generate($joins, $conditions, $grouped, $ordering, $limited, $offset);
                            }
                        }
                    }
                }
            }
        }

        $prefixed = array_map(
            static fn (string $query): bool => str_starts_with($query, 'SELECT'),
            $this->wpdb()->queries
        );

        $this->assertNotContains(false, $prefixed);
    }

    /**
     * Test the update builder assembles every optional clause combination.
     */
    public function testItAssemblesEveryOptionalUpdateClause(): void
    {
        $columns  = [
            'none'     => [],
            'single'   => ['title' => 'x'],
            'nullish'  => ['body' => null],
            'mixed'    => ['title' => 'x', 'body' => null],
        ];

        foreach ($columns as $set) {
            foreach ($this->whereShapes() as $conditions) {
                foreach ([0, 1, 2] as $ordering) {
                    foreach ([0, 1] as $limited) {
                        $builder = QuerySubject::query();

                        foreach ($conditions as $condition) {
                            $condition($builder);
                        }

                        foreach ($this->orderShapes($ordering) as $order) {
                            $order($builder);
                        }

                        if ($limited) {
                            $builder->limit($limited);
                        }

                        $builder->update($set);
                    }
                }
            }
        }

        $updates = array_filter(
            $this->wpdb()->queries,
            static fn (string $query): bool => str_starts_with($query, 'UPDATE wp_subjects SET')
        );

        // Every update is recorded twice, once by prepare() and once by query().
        $this->assertCount(192, $updates);
    }

    /**
     * Test every supported condition shape is resolved into SQL.
     */
    public function testItResolvesEveryConditionShape(): void
    {
        $shapes = [
            'empty'      => [],
            'nested-off' => [static function (QueryBuilder $builder): void {
                $builder->where(static function (QueryBuilder $inner): void {
                });
            }],
            'nested-on'  => [static function (QueryBuilder $builder): void {
                $builder->where(static function (QueryBuilder $inner): void {
                    $inner->where('id', 1)->orWhere('title', 'x');
                });
            }],
            'raw-off'    => [static function (QueryBuilder $builder): void {
                $builder->whereRaw('1 = 1')->orWhereRaw('2 = 2');
            }],
            'raw-on'     => [static function (QueryBuilder $builder): void {
                $builder->whereRaw('title = %s', ['x']);
            }],
            'in'         => [static function (QueryBuilder $builder): void {
                $builder->whereIn('id', [1, 2, 3]);
            }],
            'in-empty'   => [static function (QueryBuilder $builder): void {
                $builder->whereIn('id');
            }],
            'nulls'      => [static function (QueryBuilder $builder): void {
                $builder->whereNull('title')->whereNotNull('body');
            }],
            'column'     => [static function (QueryBuilder $builder): void {
                $builder->whereColumn('id', 'parent_id')->whereColumn('id', 'other_id', '<>');
            }],
            'exists'     => [static function (QueryBuilder $builder): void {
                $builder->where('id', 1)->whereHas('notes')
                    ->whereDoesntHave('accessory', static function (QueryBuilder $inner): void {
                        $inner->where('body', 'x');
                    });
            }],
            'scoped'     => [static function (QueryBuilder $builder): void {
                $builder->where('title', 'x', null, 'OR', 'wp_widgets');
            }],
        ];

        $expected = [
            'empty'      => '',
            'nested-off' => '',
            'nested-on'  => '(wp_subjects.id = %s OR wp_subjects.title = %s)',
            'raw-off'    => '(1 = 1) OR (2 = 2)',
            'raw-on'     => '(title = %s)',
            'in'         => 'wp_subjects.id IN (%s, %s, %s)',
            'in-empty'   => 'wp_subjects.id IN (%s)',
            'nulls'      => 'wp_subjects.title IS NULL AND wp_subjects.body IS NOT NULL',
            'column'     => '',
            'exists'     => 'wp_subjects.id = %s',
            'scoped'     => 'wp_widgets.title = %s',
        ];

        foreach ($shapes as $name => $conditions) {
            $builder = QuerySubject::query();

            foreach ($conditions as $condition) {
                $condition($builder);
            }

            $resolved = $builder->resolveWhere();

            $this->assertSame($expected[$name], implode(' ', $resolved['placeholders']), $name);
        }
    }

    /**
     * Test the column resolver joins every registered column comparison.
     */
    public function testItResolvesEveryColumnComparison(): void
    {
        $this->assertSame('', QuerySubject::query()->resolveWhereColumn());

        $resolved = QuerySubject::query()
            ->whereColumn('id', 'parent_id')
            ->whereColumn('id', '<>', 'other_id', 'OR')
            ->whereColumn('id', '>', 'third_id')
            ->resolveWhereColumn();

        $this->assertSame(
            'wp_subjects.id = parent_id OR wp_subjects.id <> other_id AND wp_subjects.id > third_id',
            $resolved
        );
    }

    /**
     * Test the exists resolver renders both the positive and the negative form.
     */
    public function testItResolvesEveryExistsShape(): void
    {
        $this->assertSame('', QuerySubject::query()->resolveWhereExists());

        $resolved = QuerySubject::query()
            ->whereHas('notes')
            ->whereDoesntHave('accessory', static function (QueryBuilder $inner): void {
                $inner->where('body', 'x');
            })
            ->resolveWhereExists();

        $this->assertStringStartsWith('EXISTS (', $resolved);
        $this->assertStringContainsString(') AND NOT EXISTS (', $resolved);
        $this->assertStringEndsWith(')', $resolved);
    }
    #endregion

    #region Eager loading combinations
    /**
     * Test to-many eager loading survives every usable and unusable key shape.
     */
    public function testItEagerLoadsToManyRelationsWithUnusableKeys(): void
    {
        $subjects = [
            'none'  => [],
            'null'  => [(object) ['id' => null]],
            'one'   => [(object) ['id' => 1]],
            'many'  => [(object) ['id' => 1], (object) ['id' => 2]],
            'text'  => [(object) ['id' => '1']],
            'float' => [(object) ['id' => 1.5]],
        ];

        $notes = [
            'none'    => [],
            'match'   => [(object) ['id' => 10, 'query_subject_id' => 1]],
            'missing' => [(object) ['id' => 11, 'query_subject_id' => null]],
            'orphan'  => [(object) ['id' => 12, 'query_subject_id' => 999]],
            'pair'    => [
                (object) ['id' => 13, 'query_subject_id' => 1],
                (object) ['id' => 14, 'query_subject_id' => '1'],
            ],
        ];

        foreach ($subjects as $rows) {
            foreach ($notes as $related) {
                $this->wpdb()->resultsResolver = static function (string $query) use ($rows, $related): array {
                    return str_contains($query, 'wp_lab_notes') ? $related : $rows;
                };

                $items = QuerySubject::query()->with('notes')->get();

                $this->assertCount(count($rows), $items);
            }
        }
    }

    /**
     * Test one-to-one eager loading survives every usable and unusable key shape.
     */
    public function testItEagerLoadsOneToOneRelationsWithUnusableKeys(): void
    {
        $subjects = [
            'none'  => [],
            'null'  => [(object) ['id' => null]],
            'one'   => [(object) ['id' => 1]],
            'many'  => [(object) ['id' => 1], (object) ['id' => 2]],
            'text'  => [(object) ['id' => '1']],
            'float' => [(object) ['id' => 1.5]],
        ];

        $widgets = [
            'none'    => [],
            'match'   => [(object) ['id' => 10, 'query_subject_id' => 1]],
            'missing' => [(object) ['id' => 11, 'query_subject_id' => null]],
            'orphan'  => [(object) ['id' => 12, 'query_subject_id' => 999]],
            'pair'    => [
                (object) ['id' => 13, 'query_subject_id' => 1],
                (object) ['id' => 14, 'query_subject_id' => 1],
            ],
        ];

        foreach ($subjects as $rows) {
            foreach ($widgets as $related) {
                $this->wpdb()->resultsResolver = static function (string $query) use ($rows, $related): array {
                    return str_contains($query, 'wp_widgets') ? $related : $rows;
                };

                $items = QuerySubject::query()->with('accessory')->get();

                $this->assertCount(count($rows), $items);
            }
        }
    }

    /**
     * Test inverse eager loading survives every usable and unusable key shape.
     */
    public function testItEagerLoadsInverseRelationsWithUnusableKeys(): void
    {
        $subjects = [
            'none'     => [],
            'null'     => [(object) ['id' => null, 'plain_record_id' => 1]],
            'match'    => [(object) ['id' => 1, 'plain_record_id' => 5]],
            'unlinked' => [(object) ['id' => 1, 'plain_record_id' => null]],
            'many'     => [
                (object) ['id' => 1, 'plain_record_id' => 5],
                (object) ['id' => '2', 'plain_record_id' => 5],
            ],
            'float'    => [(object) ['id' => 1.5, 'plain_record_id' => 5]],
        ];

        $parents = [
            'none'    => [],
            'match'   => [(object) ['id' => 5, 'title' => 'parent']],
            'missed'  => [(object) ['id' => 6, 'title' => 'other']],
            'several' => [
                (object) ['id' => 5, 'title' => 'parent'],
                (object) ['id' => 7, 'title' => 'second'],
            ],
        ];

        foreach ($subjects as $rows) {
            foreach ($parents as $related) {
                $this->wpdb()->resultsResolver = static function (string $query) use ($rows, $related): array {
                    return str_contains($query, 'wp_todos') ? $related : $rows;
                };

                $items = QuerySubject::query()->with('parent')->get();

                $this->assertCount(count($rows), $items);
            }
        }
    }
    #endregion

    #region Path completion
    /**
     * Test every optional where() argument combination resolves.
     */
    public function testWhereCoversEveryOptionalArgument(): void
    {
        QuerySubject::query()->where('title', 'x', null, 'AND')->get();
        $this->assertStringContainsString('wp_subjects.title', $this->lastQuery());

        QuerySubject::query()->where('title', '=', 'x', null, 'wp_subjects')->get();
        $this->assertStringContainsString('wp_subjects.title', $this->lastQuery());

        QuerySubject::query()->where('title', 'x', null, null, 'wp_subjects')->get();
        $this->assertStringContainsString('wp_subjects.title', $this->lastQuery());

        QuerySubject::query()->where('title', '=', 'x', 'OR', 'wp_subjects')->get();
        $this->assertStringContainsString('wp_subjects.title', $this->lastQuery());

        QuerySubject::query()->where('title', 'x', null, 'OR', 'wp_subjects')->get();
        $this->assertStringContainsString('wp_subjects.title', $this->lastQuery());

        QuerySubject::query()->where('title', '=', 'x', 'AND')->get();
        $this->assertStringContainsString('wp_subjects.title', $this->lastQuery());
    }

    /**
     * Test with() accepts a single name, a list and an empty list.
     */
    public function testWithAcceptsNamesAndLists(): void
    {
        $this->wpdb()->resultsResolver = static fn (string $query): array => [];

        QuerySubject::query()->with(['notes'])->get();
        QuerySubject::query()->with(['notes', 'parent'])->get();
        QuerySubject::query()->with(['notes', 'accessory', 'parent'])->get();
        QuerySubject::query()->with([])->get();

        $this->assertNotEmpty($this->wpdb()->queries);
    }

    /**
     * Test eager loading a parent result set without any row.
     */
    public function testItEagerLoadsWithoutParentRows(): void
    {
        $this->wpdb()->resultsResolver = static fn (string $query): array => [];

        $items = QuerySubject::query()->with('notes')->get();

        $this->assertCount(0, $items);
    }

    /**
     * Test the condition map skips nested and raw conditions.
     */
    public function testConditionMapSkipsTypedConditions(): void
    {
        QuerySubject::query()->whereRaw('1 = 1')->where('id', 1)->forceDelete();
        QuerySubject::query()->where(static function (QueryBuilder $builder): void {
            $builder->where('id', 2);
        })->where('title', 'x')->forceDelete();

        QuerySubject::query()->whereRaw('1 = 1')->forceDelete();
        QuerySubject::query()->where(static function (QueryBuilder $builder): void {
            $builder->where('id', 3);
        })->forceDelete();

        $this->assertCount(4, $this->wpdb()->deletes);
    }

    /**
     * Test ordering by more than two columns.
     */
    public function testItOrdersBySeveralColumns(): void
    {
        QuerySubject::query()->orderBy('id')->orderBy('title')->orderBy('body', 'DESC')->get();

        $this->assertStringContainsString(
            'ORDER BY id ASC, title ASC, body DESC',
            $this->lastQuery()
        );
    }

    /**
     * Test eager loading combines several relations over multiple parent rows.
     */
    public function testItEagerLoadsSeveralRelationsAtOnce(): void
    {
        $subjects = [
            (object) ['id' => 1, 'plain_record_id' => 5],
            (object) ['id' => 2, 'plain_record_id' => 5],
        ];
        $notes = [
            (object) ['id' => 10, 'query_subject_id' => 1],
            (object) ['id' => 11, 'query_subject_id' => 1],
            (object) ['id' => 12, 'query_subject_id' => 999],
        ];
        $widgets = [
            (object) ['id' => 20, 'query_subject_id' => 2],
            (object) ['id' => 21, 'query_subject_id' => 2],
        ];

        $this->wpdb()->resultsResolver = static function (string $query) use ($subjects, $notes, $widgets): array {
            if (str_contains($query, 'wp_lab_notes')) {
                return $notes;
            }

            if (str_contains($query, 'wp_widgets')) {
                return $widgets;
            }

            return $subjects;
        };

        $items = QuerySubject::query()->with(['notes', 'accessory'])->get();

        $this->assertCount(2, $items);
    }

    /**
     * Test eager loading over every parent row and relation cardinality.
     */
    public function testItEagerLoadsEveryCardinality(): void
    {
        $relationSets = [
            ['notes'],
            ['notes', 'accessory'],
            ['notes', 'accessory', 'parent', 'trashedParent'],
        ];

        foreach ([1, 2, 4] as $rowCount) {
            $subjects = [];

            for ($index = 1; $index <= $rowCount; $index++) {
                $subjects[] = (object) [
                    'id' => $index,
                    'plain_record_id' => $index,
                    'soft_delete_post_id' => $index,
                ];
            }

            foreach ($relationSets as $relations) {
                $this->wpdb()->resultsResolver = static function (string $query) use ($subjects): array {
                    if (trim($query) === 'SELECT * FROM wp_subjects') {
                        return $subjects;
                    }

                    return [];
                };

                $items = QuerySubject::query()->with($relations)->get();

                $this->assertCount($rowCount, $items);
            }
        }
    }
    #endregion

    #region Helpers
    /**
     * Return the last executed query, normalized to a single line.
     *
     * @return string The last executed query.
     */
    private function lastQuery(): string
    {
        $queries = $this->wpdb()->queries;
        $this->assertNotEmpty($queries);
        $last = end($queries);
        $this->assertIsString($last);
        $collapsed = (string) preg_replace('/\s+/', ' ', $last);

        return trim((string) preg_replace('/\s+\./', '.', $collapsed));
    }

    /**
     * Return the optional join shapes a generated query can carry.
     *
     * @return array<int, array<int, callable(QueryBuilder): void>> The join builders, keyed by join count.
     */
    private function joinShapes(): array
    {
        return [
            [static function (QueryBuilder $builder): void {
            }],
            [static function (QueryBuilder $builder): void {
                $builder->whereRelation('accessory', 'title', 'x');
            }],
            [
                static function (QueryBuilder $builder): void {
                    $builder->whereRelation('accessory', 'title', 'x');
                },
                static function (QueryBuilder $builder): void {
                    $builder->whereRelation('parent', 'title', 'y');
                },
            ],
        ];
    }

    /**
     * Return the optional condition shapes a generated query can carry.
     *
     * @return array<int, array<int, callable(QueryBuilder): void>> The condition builders.
     */
    private function whereShapes(): array
    {
        return [
            [],
            [static function (QueryBuilder $builder): void {
                $builder->where('id', 1);
            }],
            [static function (QueryBuilder $builder): void {
                $builder->whereColumn('id', 'parent_id');
            }],
            [
                static function (QueryBuilder $builder): void {
                    $builder->where('title', 'x')->whereColumn('id', 'parent_id');
                },
                static function (QueryBuilder $builder): void {
                    $builder->whereHas('notes', static function (QueryBuilder $inner): void {
                        $inner->where('body', 'y');
                    });
                },
            ],
        ];
    }

    /**
     * Return the ordering builders matching the given column count.
     *
     * @param  int $columns Number of ordered columns.
     * @return array<int, callable(QueryBuilder): void> The ordering builders.
     */
    private function orderShapes(int $columns): array
    {
        $order = [
            [],
            [static function (QueryBuilder $builder): void {
                $builder->orderBy('title');
            }],
            [
                static function (QueryBuilder $builder): void {
                    $builder->orderBy('title');
                },
                static function (QueryBuilder $builder): void {
                    $builder->orderBy('id', 'desc');
                },
            ],
        ];

        return $order[$columns];
    }

    /**
     * Run one query generation for the given clause combination.
     *
     * @param array<int, callable(QueryBuilder): void> $joins      The join builders.
     * @param array<int, callable(QueryBuilder): void> $conditions The condition builders.
     * @param bool                                      $grouped   Whether columns are grouped.
     * @param int                                       $ordering  Number of ordered columns.
     * @param int                                       $limited   Row limit, zero when unset.
     * @param int                                       $offset    Row offset, zero when unset.
     * @return void
     */
    private function generate(
        array $joins,
        array $conditions,
        bool $grouped,
        int $ordering,
        int $limited,
        int $offset
    ): void {
        foreach ([false, true] as $count) {
            $builder = QuerySubject::query();

            foreach ($joins as $join) {
                $join($builder);
            }

            foreach ($conditions as $condition) {
                $condition($builder);
            }

            if ($grouped) {
                $builder->groupBy('title');
            }

            foreach ($this->orderShapes($ordering) as $order) {
                $order($builder);
            }

            if ($limited) {
                $builder->limit($limited);
            }

            if ($offset) {
                $builder->offset($offset);
            }

            if ($count) {
                $builder->count();
                continue;
            }

            $builder->get();
        }
    }
    #endregion
}
