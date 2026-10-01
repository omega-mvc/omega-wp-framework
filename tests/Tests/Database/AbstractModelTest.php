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

use Omega\Collection\Collection;
use Omega\Database\Database;
use Omega\Database\ORM\AbstractModel;
use Omega\Database\ORM\Casts\Attribute;
use Omega\Database\ORM\Casts\BooleanCast;
use Omega\Database\ORM\QueryBuilder;
use Omega\Database\ORM\Relations\BelongsTo;
use Omega\Database\ORM\Relations\HasMany;
use Omega\Database\ORM\Relations\HasOne;
use Omega\Paginator\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use stdClass;
use Tests\Database\Fixtures\AccessoredWidget;
use Tests\Database\Fixtures\Article;
use Tests\Database\Fixtures\CastedArticle;
use Tests\Database\Fixtures\ImplicitRecord;
use Tests\Database\Fixtures\InstancedCastItem;
use Tests\Database\Fixtures\SoftDeletePost;
use Tests\Database\Fixtures\TimestampedNote;

/**
 * Unit tests for the AbstractModel base active record implementation.
 *
 * @category  Tests
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractModel::class)]
final class AbstractModelTest extends DatabaseTestCase
{
    /**
     * Test the model table name is derived from the class short name.
     */
    public function testModelToTableGeneratesPluralSnakeCaseName(): void
    {
        $this->assertSame('articles', Article::modelToTable(Article::class));
        $this->assertSame('articles', Article::modelToTable(new Article()));
    }

    /**
     * Test the static foreign key is derived from the class short name.
     */
    public function testGetForeignKeyStaticGeneratesSnakeCaseId(): void
    {
        $this->assertSame('article_id', Article::getForeignKeyStatic());
        $this->assertSame('accessored_widget_id', AccessoredWidget::getForeignKeyStatic());
    }

    /**
     * Test the instance foreign key base name is derived from the class short name.
     */
    public function testInstanceDerivesTableAndForeignKeyFromClassName(): void
    {
        $article = new Article();

        $this->assertSame('wp_articles', $article->getTableName());
        $this->assertSame('wp_articles', Article::getTable());
        $this->assertSame('wp_articles', Article::getFullTableName());
        $this->assertSame('article', $article->getForeignKey());
        $this->assertSame('', Article::getPrefix());
        $this->assertSame('id', $article->getPrimaryKey());
    }

    /**
     * Test an explicit table name and custom prefix take precedence.
     */
    public function testExplicitTableAndPrefixAreHonoured(): void
    {
        $note = new TimestampedNote();

        $this->assertSame('wp_lab_notes', $note->getTableName());
        $this->assertSame('wp_lab_notes', TimestampedNote::getFullTableName());
        $this->assertSame('lab_', TimestampedNote::getPrefix());
        $this->assertSame('timestamped_note', $note->getForeignKey());
    }

    /**
     * Test the constructor accepts a custom table name override.
     */
    public function testConstructorAcceptsCustomTableOverride(): void
    {
        $article = new Article([], 'custom_articles');

        $this->assertSame('custom_articles', $article->getTableName());
    }

    /**
     * Test timestamps usage is detected from the model property.
     */
    public function testUsesTimestampsDetection(): void
    {
        $this->assertFalse(Article::usesTimestamps());
        $this->assertTrue(TimestampedNote::usesTimestamps());
    }

    /**
     * Test the shared instance is created once per concrete model class.
     */
    public function testGetInstanceCachesOneInstancePerClass(): void
    {
        $first  = Article::getInstance();
        $second = Article::getInstance();

        $this->assertInstanceOf(Article::class, $first);
        $this->assertSame($first, $second);
        $this->assertNotSame($first, TimestampedNote::getInstance());
    }

    /**
     * Test getDefaultPropertyValue returns the fallback for unknown properties.
     */
    public function testGetDefaultPropertyValueReturnsFallback(): void
    {
        $this->assertSame('notes', AbstractModel::getDefaultPropertyValue(TimestampedNote::class, 'table'));
        $this->assertSame(
            'notes',
            AbstractModel::getDefaultPropertyValue(new TimestampedNote(), 'table')
        );
        $this->assertNull(AbstractModel::getDefaultPropertyValue(TimestampedNote::class, 'unknown_property'));
        $this->assertSame('fallback', AbstractModel::getDefaultPropertyValue(
            TimestampedNote::class,
            'unknown_property',
            'fallback'
        ));
    }

    /**
     * Test the database manager is exposed by the model.
     */
    public function testGetDatabaseReturnsDatabaseManager(): void
    {
        $this->assertInstanceOf(Database::class, (new Article())->getDatabase());
    }

    /**
     * Test the retrieved flag can be toggled and is reported back.
     */
    public function testWasRetrievedFlagIsMutable(): void
    {
        $article = new Article();
        $this->assertFalse($article->wasRetrieved());

        $article->setWasRetrieved(true);
        $this->assertTrue($article->wasRetrieved());
    }

    /**
     * Test soft delete usage is reported for both instance and static calls.
     */
    public function testTrashedDetection(): void
    {
        $this->assertFalse((new Article())->trashed());
        $this->assertFalse(Article::isTrashed());
        $this->assertTrue((new SoftDeletePost())->trashed());
        $this->assertTrue(SoftDeletePost::isTrashed());
    }

    /**
     * Test raw attribute assignment bypasses casts and mutators.
     */
    public function testSetAttributeStoresRawValue(): void
    {
        $article = new Article();
        $article->setAttribute('views', '42');

        $this->assertSame('42', $article->toArray()['views']);
        $this->assertSame(42, $this->getAttributeValue($article, 'views', '42'));
        $this->assertTrue($article->keyExists('views'));
        $this->assertFalse($article->keyExists('unknown'));
    }

    /**
     * Test primitive "int" casts coerce scalar values and zero out others.
     */
    public function testIntegerCastCoercion(): void
    {
        $article = new Article(['views' => '12']);

        $this->assertSame(12, $article->toArray()['views']);
        $this->assertSame(7, $this->getAttributeValue($article, 'views', 7));
        $this->assertSame(0, $this->getAttributeValue($article, 'views', ['nope']));
        $this->assertSame(7, $this->setAttributeValue($article, 'views', '7'));
        $this->assertSame(0, $this->setAttributeValue($article, 'views', new stdClass()));
    }

    /**
     * Test primitive "real" casts coerce scalar values and zero out others.
     */
    public function testRealCastCoercion(): void
    {
        $article = new Article(['rating' => '2.5']);

        $this->assertSame(2.5, $article->toArray()['rating']);
        $this->assertSame(1.5, $this->getAttributeValue($article, 'rating', 1.5));
        $this->assertSame(0.0, $this->getAttributeValue($article, 'rating', new stdClass()));
        $this->assertSame(2.5, $this->setAttributeValue($article, 'rating', '2.5'));
        $this->assertSame(0.0, $this->setAttributeValue($article, 'rating', new stdClass()));
    }

    /**
     * Test primitive "string" casts handle null, scalars and non scalars.
     */
    public function testStringCastCoercion(): void
    {
        $article = new Article(['title' => 123]);

        $this->assertSame('123', $article->toArray()['title']);
        $this->assertNull($this->getAttributeValue($article, 'title', null));
        $this->assertSame('', $this->getAttributeValue($article, 'title', ['nope']));
        $this->assertSame('x', $this->setAttributeValue($article, 'title', 'x'));
        $this->assertNull($this->setAttributeValue($article, 'title', null));
        $this->assertSame('', $this->setAttributeValue($article, 'title', ['nope']));
    }

    /**
     * Test the "bool" alias resolves to the boolean cast class.
     */
    public function testBooleanAliasResolvesToCastClass(): void
    {
        $article = new Article();

        $this->assertTrue($this->getAttributeValue($article, 'live', 1));
        $this->assertFalse($this->getAttributeValue($article, 'live', 0));
        $this->assertSame(1, $this->setAttributeValue($article, 'live', 1));
        $this->assertSame(0, $this->setAttributeValue($article, 'live', new stdClass()));
    }

    /**
     * Test the "array" alias resolves to the array cast class.
     */
    public function testArrayAliasResolvesToCastClass(): void
    {
        $article = new Article();

        $this->assertSame(['a' => 1], $this->getAttributeValue($article, 'meta', '{"a":1}'));
        $this->assertNull($this->getAttributeValue($article, 'meta', ['not', 'scalar']));
        $this->assertSame('{"a":1}', $this->setAttributeValue($article, 'meta', ['a' => 1]));
    }

    /**
     * Test the "money" alias resolves to the money cast class.
     */
    public function testMoneyAliasResolvesToCastClass(): void
    {
        $article = new Article();

        $this->assertSame(10.0, $this->getAttributeValue($article, 'price', '1000'));
        $this->assertSame(100000, $this->setAttributeValue($article, 'price', '1000'));
    }

    /**
     * Test a cast configured with an existing cast class name is instantiated.
     */
    public function testExistingCastClassNameIsInstantiated(): void
    {
        $article = new CastedArticle();

        $this->assertTrue($this->getAttributeValue($article, 'flag', 1));
        $this->assertSame(1, $this->setAttributeValue($article, 'flag', 1));
    }

    /**
     * Test an unknown cast alias that is not an existing class leaves the value untouched.
     */
    public function testUnknownCastAliasLeavesValueUntouched(): void
    {
        $article = new Article(['custom' => 'kept']);

        $this->assertSame('kept', $article->toArray()['custom']);
        $this->assertSame('other', $this->getAttributeValue($article, 'custom', 'other'));
        $this->assertSame('other', $this->setAttributeValue($article, 'custom', 'other'));
    }

    /**
     * Test a cast definition that is neither a string nor a cast object is ignored.
     */
    public function testUnsupportedCastDefinitionIsIgnored(): void
    {
        $article = new Article();

        $this->assertSame('kept', $this->getAttributeValue($article, 'missing_key', 'kept'));
    }

    /**
     * Test a cast definition that resolves to no cast at all leaves the value untouched.
     */
    public function testCastDefinitionWithoutAnyHandlerLeavesTheValueUntouched(): void
    {
        $record = new ImplicitRecord();

        $this->assertSame('kept', $this->getAttributeValue($record, 'marker', 'kept'));
        $this->assertSame('stored', $this->setAttributeValue($record, 'marker', 'stored'));
    }

    /**
     * Test an empty table name falls back to the name derived from the class.
     */
    public function testEmptyTableNameFallsBackToTheDerivedOne(): void
    {
        $this->assertSame('wp_implicit_records', ImplicitRecord::getFullTableName());
    }

    /**
     * Test an uncasted attribute is returned as is.
     */
    public function testUncastedAttributeIsReturnedAsIs(): void
    {
        $article = new Article(['body' => 'raw body']);

        $this->assertSame('raw body', $this->getAttributeValue($article, 'body', 'raw body'));
        $this->assertNull($this->getAttributeValue($article, 'body', null));
        $this->assertSame('raw body', $this->setAttributeValue($article, 'body', 'raw body'));
    }

    /**
     * Test an Attribute accessor transforms the value on read.
     */
    public function testAttributeAccessorTransformsValueOnRead(): void
    {
        $widget = new AccessoredWidget(['label' => 'alpha']);

        $this->assertSame('label:alpha', $widget->toArray()['label']);
        $this->assertSame('label:alpha', $this->getAttributeValue($widget, 'label', 'alpha'));
        $this->assertSame('label:label:alpha', $this->getAttributeValue($widget, 'label', null));
    }

    /**
     * Test an Attribute mutator transforms the value on write.
     */
    public function testAttributeMutatorTransformsValueOnWrite(): void
    {
        $widget = new AccessoredWidget();

        $this->assertSame('alpha', $this->setAttributeValue($widget, 'slug', 'ALPHA'));
        $this->assertSame('SLUG', $this->getAttributeValue($widget, 'slug', 'slug'));
        $this->assertSame('kept', $this->setAttributeValue($widget, 'label', 'kept'));
    }

    /**
     * Test an accessor shaped method returning a non Attribute value is ignored.
     */
    public function testNonAttributeAccessorShapedMethodIsIgnored(): void
    {
        $widget = new AccessoredWidget();

        $this->assertSame('kept', $this->getAttributeValue($widget, 'plain', 'kept'));
        $this->assertSame('kept', $this->setAttributeValue($widget, 'plain', 'kept'));
    }

    /**
     * Test the resolved attribute method is derived from the snake case key.
     */
    public function testGetAttributeMethodResolvesAccessorName(): void
    {
        $widget = new AccessoredWidget();

        $this->assertSame('label', $this->invokeMethod($widget, 'getAttributeMethod', 'label'));
        $this->assertNull($this->invokeMethod($widget, 'getAttributeMethod', 'missing_key'));
    }

    /**
     * Test the cast definitions are exposed to child models.
     */
    public function testCastsAreExposed(): void
    {
        $casts = $this->invokeArrayMethod(new Article(), 'casts');

        $this->assertSame('int', $casts['views']);
    }

    /**
     * Test magic property read prefers raw data and falls back to the object scope.
     */
    public function testMagicGetPrefersRawDataThenFallsBack(): void
    {
        $article = new Article(['views' => '5']);

        $this->assertSame(5, $article->views);
        $this->assertSame(['title', 'body'], $article->fillable);
    }

    /**
     * Test magic property write applies casts and stores the transformed value.
     */
    public function testMagicSetAppliesCastsOnNewModels(): void
    {
        $article = new Article();
        $article->views = '15';

        $this->assertSame(15, $article->views);
        $this->assertSame(15, $article->toArray()['views']);
    }

    /**
     * Test magic property write stages the value for retrieved models.
     */
    public function testMagicSetStagesValuesForRetrievedModels(): void
    {
        $retrieved = new Article(['id' => 1, 'views' => '1']);
        $retrieved->setWasRetrieved(true);
        $retrieved->views = '20';

        $this->assertSame(1, $retrieved->views);
        $this->assertSame(1, $retrieved->save());
        $this->assertStringContainsString('views = ', $this->wpdb()->queries[0]);
    }

    /**
     * Test magic isset reflects the presence of the raw attribute.
     */
    public function testMagicIssetReflectsRawData(): void
    {
        $article = new Article(['body' => 'text']);

        $this->assertTrue(isset($article->body));
        $this->assertFalse(isset($article->missing_attribute));
    }

    /**
     * Test array access read paths for existing, casted and missing offsets.
     */
    public function testOffsetGetResolvesValues(): void
    {
        $widget = new AccessoredWidget(['label' => 'beta']);

        $this->assertSame('label:beta', $widget['label']);
        $this->assertSame(3, (new Article(['views' => '3']))->offsetGet('views'));
        $this->assertNull($widget->offsetGet('missing_attribute'));
        $this->assertSame('label:', (new AccessoredWidget([]))->offsetGet('label'));
    }

    /**
     * Test array access existence checks ignore the cast layer.
     */
    public function testOffsetExistsReflectsRawData(): void
    {
        $widget = new AccessoredWidget(['label' => 'beta']);
        $widget[] = 'appended';

        $this->assertTrue($widget->offsetExists('label'));
        $this->assertTrue($widget->offsetExists(0));
        $this->assertFalse($widget->offsetExists(new stdClass()));
        $this->assertFalse($widget->offsetExists('missing'));
    }

    /**
     * Test array access write paths for new and retrieved models.
     */
    public function testOffsetSetWritesAttributes(): void
    {
        $article = new Article();
        $article->offsetSet('views', '9');
        $this->assertSame(9, $article->views);

        $article->offsetSet(null, 'skipped');
        $this->assertFalse($article->keyExists(''));

        $retrieved        = new Article();
        $retrieved->setWasRetrieved(true);
        $retrieved->offsetSet('views', '4');

        $this->assertFalse($retrieved->keyExists('views'));
    }

    /**
     * Test array access unset removes the raw attribute.
     */
    public function testOffsetUnsetRemovesAttribute(): void
    {
        $article = new Article(['body' => 'text']);

        $article->offsetUnset('body');
        $this->assertFalse($article->keyExists('body'));

        $article->offsetUnset(new stdClass());
        $this->assertFalse($article->keyExists(''));
    }

    /**
     * Test toArray recursively serializes nested models and collections.
     */
    public function testToArraySerializesNestedValues(): void
    {
        $related    = new Article(['id' => 1, 'title' => 'inner']);
        $collection = new Collection([$related, 'plain']);
        $article    = new Article(['id' => 2, 'title' => 'outer', 'nested' => $related, 'list' => $collection]);

        $result = $article->toArray();

        $this->assertSame(['id' => 1, 'title' => 'inner'], $result['nested']);
        $this->assertSame([['id' => 1, 'title' => 'inner'], 'plain'], $result['list']);
        $this->assertSame('outer', $result['title']);
    }

    /**
     * Test the static query helpers build a query builder for the model.
     */
    public function testStaticQueryHelpersBuildQueryBuilder(): void
    {
        $this->assertInstanceOf(QueryBuilder::class, Article::query());
        $this->assertInstanceOf(QueryBuilder::class, (new Article())->getQueryBuilder());
        $this->assertInstanceOf(QueryBuilder::class, Article::with('comments'));
        $this->assertInstanceOf(QueryBuilder::class, Article::where('id', 1));
        $this->assertInstanceOf(QueryBuilder::class, Article::whereNull('deleted_at'));
        $this->assertInstanceOf(QueryBuilder::class, Article::whereNull(new stdClass()));
        $this->assertInstanceOf(QueryBuilder::class, Article::whereHas('comments', static fn ($q) => $q));
        $this->assertInstanceOf(QueryBuilder::class, Article::select('id'));
        $this->assertInstanceOf(QueryBuilder::class, Article::whereIn('id', [1, 2]));
        $this->assertInstanceOf(QueryBuilder::class, Article::when(false, static fn ($q) => $q));
    }

    /**
     * Test the conditional query helper applies the callback and validates its result.
     */
    public function testWhenAppliesCallbackAndFallsBackOnInvalidResult(): void
    {
        $applied = Article::when(true, static fn (QueryBuilder $q): QueryBuilder => $q);
        $this->assertInstanceOf(QueryBuilder::class, $applied);

        $this->assertInstanceOf(QueryBuilder::class, Article::when(true, static fn (): string => 'nope'));
    }

    /**
     * Test all() returns a collection of hydrated models.
     */
    public function testAllReturnsHydratedCollection(): void
    {
        $this->wpdb()->results = [(object) ['id' => 1, 'title' => 'first']];

        $all = Article::all();

        $this->assertInstanceOf(Collection::class, $all);
        $this->assertCount(1, $all);
        $this->assertInstanceOf(Article::class, $all->getAll()[0]);
        $this->assertTrue($all->getAll()[0]->wasRetrieved());
    }

    /**
     * Test count() delegates to the query builder count query.
     */
    public function testCountDelegatesToQueryBuilder(): void
    {
        $this->wpdb()->varValue = '4';

        $this->assertSame(4, Article::count());
    }

    /**
     * Test find() returns null when no record matches.
     */
    public function testFindReturnsNullWithoutResults(): void
    {
        $this->assertNull(Article::find(123));
    }

    /**
     * Test find() hydrates the matching record.
     */
    public function testFindHydratesMatchingRecord(): void
    {
        $this->wpdb()->results = [(object) ['id' => 7, 'title' => 'found']];

        $found = Article::find(7);

        $this->assertInstanceOf(Article::class, $found);
        $this->assertSame('found', $found->title);
    }

    /**
     * Test paginate() falls back to the default page size for non numeric values.
     */
    public function testPaginateBuildsPaginatorWithDefaultPageSize(): void
    {
        $this->wpdb()->varValue = '3';

        $paginator = Article::paginate('abc');

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertSame(3, $paginator->getAttributes()['total']);
        $this->assertSame(15, $paginator->getAttributes()['per_page']);
    }

    /**
     * Test paginate() accepts a numeric page size.
     */
    public function testPaginateAcceptsNumericPageSize(): void
    {
        $paginator = Article::paginate(5);

        $this->assertSame(5, $paginator->getAttributes()['per_page']);
        $this->assertSame(1, $paginator->getAttributes()['current_page']);
    }

    /**
     * Test create() inserts the row, applies casts and returns the new model.
     */
    public function testCreateInsertsAndReturnsModel(): void
    {
        $this->wpdb()->nextInsertId = 11;

        $article = Article::create(['title' => 123, 'views' => '8']);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertTrue($article->wasRetrieved());
        $this->assertSame(11, $article->id);
        $this->assertSame('wp_articles', $this->wpdb()->inserts[0][0]);
        $this->assertSame(8, $this->wpdb()->inserts[0][1]['views']);
    }

    /**
     * Test create() populates the timestamp columns when enabled.
     */
    public function testCreatePopulatesTimestamps(): void
    {
        TimestampedNote::create(['body' => 'note']);

        $this->assertArrayHasKey('created_at', $this->wpdb()->inserts[0][1]);
        $this->assertArrayHasKey('updated_at', $this->wpdb()->inserts[0][1]);
    }

    /**
     * Test create() returns false when the insert fails.
     */
    public function testCreateReturnsFalseOnInsertFailure(): void
    {
        $this->wpdb()->failNext = true;

        $this->assertFalse(Article::create(['title' => 'nope']));
    }

    /**
     * Test create() returns false on a timestamped model when the insert fails.
     */
    public function testCreateReturnsFalseOnATimestampedInsertFailure(): void
    {
        $this->wpdb()->failNext = true;

        $this->assertFalse(TimestampedNote::create(['body' => 'nope']));
    }

    /**
     * Test create() returns false for every payload size on a failing insert.
     */
    public function testCreateReturnsFalseForEveryPayloadSize(): void
    {
        $payloads = [
            [],
            ['title' => 'a'],
            ['title' => 'a', 'views' => 1],
            ['title' => 'a', 'views' => 1, 'body' => 'b'],
        ];

        foreach ($payloads as $payload) {
            $this->wpdb()->failNext = true;
            $this->assertFalse(Article::create($payload));

            $this->wpdb()->failNext = true;
            $this->assertFalse(TimestampedNote::create($payload));
        }
    }

    /**
     * Test update() forwards the values to the database manager.
     */
    public function testUpdateForwardsValuesToDatabase(): void
    {
        $this->assertSame(1, Article::update(['title' => 'new'], ['id' => 3]));
        $this->assertSame('wp_articles', $this->wpdb()->updates[0][0]);
        $this->assertSame(['title' => 'new'], $this->wpdb()->updates[0][1]);
        $this->assertSame(['id' => 3], $this->wpdb()->updates[0][2]);
    }

    /**
     * Test updateOrCreate() creates the record when no match exists.
     */
    public function testUpdateOrCreateCreatesWhenMissing(): void
    {
        $this->wpdb()->nextInsertId = 5;

        $result = Article::updateOrCreate(['slug' => 'x'], ['title' => 'y']);

        $this->assertInstanceOf(Article::class, $result);
        $this->assertCount(1, $this->wpdb()->inserts);
    }

    /**
     * Test updateOrCreate() updates the record when a match exists.
     */
    public function testUpdateOrCreateUpdatesWhenFound(): void
    {
        $this->wpdb()->results = [(object) ['id' => 9, 'slug' => 'x']];

        $result = Article::updateOrCreate(['slug' => 'x'], ['title' => 'y']);

        $this->assertIsInt($result);
        $this->assertCount(1, $this->wpdb()->updates);
        $this->assertCount(0, $this->wpdb()->inserts);
    }

    /**
     * Test fill() only stages the fillable attributes.
     */
    public function testFillStagesOnlyFillableAttributes(): void
    {
        $article = new Article(['id' => 1]);
        $article->setWasRetrieved(true);
        $article->fill(['title' => 'kept', 'views' => 'dropped']);

        $this->assertSame(1, $article->save());
        $this->assertStringNotContainsString('views', $this->wpdb()->queries[0]);
    }

    /**
     * Test fill() stages every attribute when the fillable list is empty.
     */
    public function testFillStagesEverythingWithoutFillableList(): void
    {
        $note = new TimestampedNote(['id' => 2]);
        $note->setWasRetrieved(true);
        $note->fill(['body' => 'anything']);

        $this->assertSame(1, $note->save());
        $this->assertStringContainsString('body = ', $this->wpdb()->queries[0]);
    }

    /**
     * Test fill() handles payloads of every size.
     */
    public function testFillHandlesEveryPayloadSize(): void
    {
        $payloads = [
            [],
            ['body' => 'a'],
            ['body' => 'a', 'extra' => 'b'],
            ['body' => 'a', 'extra' => 'b', 'more' => 'c'],
            ['body' => 'a', 'extra' => 'b', 'more' => 'c', 'other' => 'd'],
            ['body' => 'a', 'extra' => 'b', 'more' => 'c', 'other' => 'd', 'last' => 'e'],
        ];

        foreach ($payloads as $payload) {
            $article = new Article();
            $article->fill($payload);

            $note = new TimestampedNote();
            $note->fill($payload);

            $this->assertInstanceOf(Article::class, $article);
            $this->assertInstanceOf(TimestampedNote::class, $note);
        }
    }

    /**
     * Test save() inserts new models and assigns the generated primary key.
     */
    public function testSaveInsertsNewModel(): void
    {
        $this->wpdb()->nextInsertId = 21;

        $note = new TimestampedNote(['body' => 'text']);

        $this->assertSame(21, $note->save());
        $this->assertSame(21, $note->id);
    }

    /**
     * Test save() updates retrieved models with the staged attributes.
     */
    public function testSaveUpdatesRetrievedModel(): void
    {
        $note = new TimestampedNote(['id' => 4, 'body' => 'old']);
        $note->setWasRetrieved(true);
        $note->fill(['body' => 'new']);

        $this->assertSame(1, $note->save());
        $this->assertSame('new', $note->body);
    }

    /**
     * Test save() leaves the model untouched when the update fails.
     */
    public function testSaveKeepsDataWhenUpdateFails(): void
    {
        $note = new TimestampedNote(['id' => 4, 'body' => 'old']);
        $note->setWasRetrieved(true);
        $note->fill(['body' => 'new']);

        $this->wpdb()->failNext = true;

        $this->assertFalse($note->save());
        $this->assertSame('old', $note->body);
    }

    /**
     * Test save() returns false when the insert fails.
     */
    public function testSaveReturnsFalseOnInsertFailure(): void
    {
        $this->wpdb()->failNext = true;

        $this->assertFalse((new TimestampedNote(['body' => 'text']))->save());
    }

    /**
     * Test delete() removes a retrieved record.
     */
    public function testDeleteRemovesRetrievedRecord(): void
    {
        $note = new TimestampedNote(['id' => 8]);
        $note->setWasRetrieved(true);

        $this->assertSame(1, $note->delete());
        $this->assertSame('wp_lab_notes', $this->wpdb()->deletes[0][0]);
        $this->assertSame(['id' => 8], $this->wpdb()->deletes[0][1]);
    }

    /**
     * Test delete() rejects models that were not retrieved.
     */
    public function testDeleteRejectsModelNotRetrieved(): void
    {
        $note = new TimestampedNote(['id' => 8]);

        $this->assertFalse($note->delete());
    }

    /**
     * Test createMany() issues a single bulk insert statement.
     */
    public function testCreateManyInsertsEveryRow(): void
    {
        $this->assertSame(1, Article::createMany([['title' => 'a'], ['title' => 'b']]));
        $this->assertStringContainsString('INSERT INTO wp_articles', $this->wpdb()->queries[0]);
    }

    /**
     * Test the relationship factories build the configured relation objects.
     */
    public function testRelationshipFactoriesBuildRelations(): void
    {
        $article = new Article();

        $this->assertInstanceOf(HasOne::class, $article->hasOne(Article::class));
        $this->assertInstanceOf(HasMany::class, $article->hasMany(Article::class));
        $this->assertInstanceOf(BelongsTo::class, $article->belongsTo(Article::class));
        $this->assertInstanceOf(HasMany::class, $article->comments());
        $this->assertInstanceOf(HasOne::class, $article->widget());
    }

    /**
     * Test relationLoaded reflects the raw attribute presence.
     */
    public function testRelationLoadedReflectsRawData(): void
    {
        $article = new Article(['posts' => 'loaded']);

        $this->assertTrue($article->relationLoaded('posts'));
        $this->assertFalse($article->relationLoaded('comments'));
    }

    /**
     * Test an Attribute object exposes the configured callables.
     */
    public function testAttributeObjectKeepsCallables(): void
    {
        $attribute = Attribute::make(static fn ($value) => $value, static fn ($value) => $value);

        $this->assertNotNull($attribute->get);
        $this->assertNotNull($attribute->set);
        $this->assertFalse($attribute->withCaching);
        $this->assertTrue($attribute->withObjectCaching);
    }

    /**
     * Test a cast configured as a ready to use instance is applied as is.
     */
    public function testCastConfiguredAsInstanceIsApplied(): void
    {
        $model = new InstancedCastItem();

        $this->assertInstanceOf(BooleanCast::class, $this->invokeArrayMethod($model, 'casts')['state']);
        $this->assertFalse($model->state);
        $this->assertSame(1, $this->setAttributeValue($model, 'state', '1'));
    }

    /**
     * Test a write only accessor lets the cast layer resolve the read value.
     */
    public function testWriteOnlyAccessorFallsBackToCastLayer(): void
    {
        $widget = new AccessoredWidget();

        $this->assertSame('code:abc', $this->setAttributeValue($widget, 'code', 'abc'));
        $this->assertSame('abc', $this->getAttributeValue($widget, 'code', 'abc'));
        $this->assertNull($this->getAttributeValue($widget, 'code'));
    }

    /**
     * Test magic property read resolves the cast when the raw attribute is missing.
     */
    public function testMagicGetResolvesCastForMissingRawAttribute(): void
    {
        $article = new Article();

        $this->assertFalse($article->keyExists('views'));
        $this->assertSame(0, $article->views);
    }

    /**
     * Test array access resolves integer offsets against the raw data.
     */
    public function testArrayAccessWithIntegerOffsets(): void
    {
        $article = new Article();
        $article->offsetSet(0, 'zero');
        $article->offsetSet(1, 'one');

        $this->assertTrue($article->offsetExists(0));
        $this->assertSame('zero', $article->offsetGet(0));
        $this->assertSame(['zero', 'one'], $article->toArray());

        $article->offsetUnset(0);

        $this->assertFalse($article->offsetExists(0));
        $this->assertNull($article->offsetGet(0));
    }

    /**
     * Test an object offset collapses into the empty string key.
     */
    public function testArrayAccessWithObjectOffsetCollapsesIntoEmptyKey(): void
    {
        $article = new Article();
        $article->offsetSet(new stdClass(), 'orphan');

        $this->assertTrue($article->offsetExists(new stdClass()));
        $this->assertSame('orphan', $article->offsetGet(new stdClass()));
        $this->assertSame(['' => 'orphan'], $article->toArray());
    }

    /**
     * Test array access stages the collapsed key for retrieved models.
     */
    public function testArrayAccessWithNullOffsetStagesRawValue(): void
    {
        $retrieved = new Article();
        $retrieved->setWasRetrieved(true);
        $retrieved->offsetSet(null, 'raw');
        $retrieved->offsetSet(new stdClass(), 'staged');

        $this->assertFalse($retrieved->keyExists(''));

        $appended = new Article();
        $appended[] = 'appended';

        $this->assertSame(['appended'], $appended->toArray());
        $this->assertSame('appended', $appended[0]);
    }

    /**
     * Test create() accepts an empty payload and still returns a model.
     */
    public function testCreateWithEmptyPayload(): void
    {
        $this->wpdb()->nextInsertId = 31;

        $article = Article::create([]);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame(31, $article->id);
        $this->assertSame([], $this->wpdb()->inserts[0][1]);
    }

    /**
     * Test create() on a timestamped model with an empty payload.
     */
    public function testCreateWithEmptyPayloadAndTimestamps(): void
    {
        $note = TimestampedNote::create([]);

        $this->assertInstanceOf(TimestampedNote::class, $note);
        $this->assertArrayHasKey('created_at', $this->wpdb()->inserts[0][1]);
        $this->assertArrayHasKey('updated_at', $this->wpdb()->inserts[0][1]);
    }

    /**
     * Test fill() keeps every fillable attribute of a mixed payload.
     */
    public function testFillKeepsEveryFillableAttributeOfMixedPayload(): void
    {
        $article = new Article(['id' => 1]);
        $article->setWasRetrieved(true);
        $article->fill(['title' => 'kept', 'body' => 'kept too', 'views' => '1', 'other' => '2']);

        $this->assertSame(1, $article->save());
        $this->assertStringContainsString('title = ', $this->wpdb()->queries[0]);
        $this->assertStringContainsString('body = ', $this->wpdb()->queries[0]);
        $this->assertStringNotContainsString('views', $this->wpdb()->queries[0]);
        $this->assertStringNotContainsString('other', $this->wpdb()->queries[0]);
    }

    /**
     * Test toArray() serializes empty and flat payloads.
     */
    public function testToArraySerializesEmptyAndFlatPayloads(): void
    {
        $this->assertSame([], (new Article())->toArray());
        $this->assertSame(['title' => 'flat'], (new Article(['title' => 'flat']))->toArray());
    }

    /**
     * Invoke the private read pipeline for the given attribute.
     *
     * @param AbstractModel $model The model instance.
     * @param string $key The attribute name.
     * @param mixed $value The optional raw attribute value.
     * @return mixed The resolved attribute value.
     */
    private function getAttributeValue(AbstractModel $model, string $key, mixed $value = null): mixed
    {
        return $this->invokeMethod($model, 'getAttributeValue', $key, $value);
    }

    /**
     * Invoke the private write pipeline for the given attribute.
     *
     * @param AbstractModel $model The model instance.
     * @param string $key The attribute name.
     * @param mixed $value The raw attribute value.
     * @return mixed The transformed attribute value.
     */
    private function setAttributeValue(AbstractModel $model, string $key, mixed $value): mixed
    {
        return $this->invokeMethod($model, 'setAttributeValue', $key, $value);
    }

    /**
     * Invoke a protected or private method on the given instance.
     *
     * @param object $target The instance to invoke the method on.
     * @param string $method The method name.
     * @param mixed ...$arguments The method arguments.
     * @return mixed The value returned by the invoked method.
     */
    private function invokeMethod(object $target, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($target, $method))->invoke($target, ...$arguments);
    }

    /**
     * Invoke a method and assert that it returns an array.
     *
     * @param object $target The object owning the method.
     * @param string $method The method name.
     * @param mixed ...$arguments The arguments passed to the method.
     * @return array<array-key, mixed> The array returned by the method.
     */
    private function invokeArrayMethod(object $target, string $method, mixed ...$arguments): array
    {
        $result = $this->invokeMethod($target, $method, ...$arguments);

        $this->assertIsArray($result);

        return $result;
    }
}
