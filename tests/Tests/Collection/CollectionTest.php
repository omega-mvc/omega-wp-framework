<?php

/**
 * Part of Omega - Tests Collection Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Collection;

use ArrayObject;
use Omega\Application\Application;
use Omega\Application\ApplicationFactory;
use Omega\Collection\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Tests\FixturesPathTrait;
use Tests\Http\Support\FakeModel;

/**
 * Tests the Collection wrapper behaviour.
 *
 * @category  Tests
 * @package   Collection
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Collection::class)]
final class CollectionTest extends TestCase
{
    use FixturesPathTrait;

    /**
     * Register an application so AbstractModel-based fixtures resolve.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, ['app' => new Application('app', $this->setFixturePath('/fixtures/app/theme'))]);
    }

    /**
     * Clear the shared factory registry.
     */
    protected function tearDown(): void
    {
        $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
        $property->setValue(null, []);

        parent::tearDown();
    }

    /**
     * Test each() visits every item with its key and returns the same instance.
     */
    public function testEachVisitsItemsWithKeysAndReturnsSameInstance(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);
        $visited    = [];

        $result = $collection->each(function ($item, $key) use (&$visited): void {
            $visited[$key] = $item;
        });

        $this->assertSame(['a' => 1, 'b' => 2], $visited);
        $this->assertSame($collection, $result);
    }

    /**
     * Test each() stops iterating when the callback returns false.
     */
    public function testEachStopsWhenCallbackReturnsFalse(): void
    {
        $collection = new Collection([1, 2, 3]);
        $visited    = [];

        $collection->each(function ($item) use (&$visited) {
            $visited[] = $item;

            return $item < 2;
        });

        $this->assertSame([1, 2], $visited);
    }

    /**
     * Test each() on an empty collection performs no iterations and returns the same instance.
     */
    public function testEachOnEmptyCollection(): void
    {
        $collection = new Collection();
        $visited    = [];

        $result = $collection->each(function ($item) use (&$visited): void {
            $visited[] = $item;
        });

        $this->assertSame([], $visited);
        $this->assertSame($collection, $result);
    }

    /**
     * Test map() transforms items, keeps keys, and returns a plain array.
     */
    public function testMapTransformsItemsAndKeepsKeys(): void
    {
        $result = (new Collection(['a' => 1, 'b' => 2]))->map(fn ($item, $key) => $key . ':' . $item);

        $this->assertSame(['a' => 'a:1', 'b' => 'b:2'], $result);
        $this->assertIsArray($result);
    }

    /**
     * Test pluck() extracts values with numeric reindexing.
     */
    public function testPluckExtractsValuesWithNumericIndexing(): void
    {
        $items = [
            (object) ['id' => 1, 'name' => 'Ada'],
            (object) ['id' => 2, 'name' => 'Grace'],
        ];

        $result = (new Collection($items))->pluck('name');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame(['Ada', 'Grace'], $result->getAll());
    }

    /**
     * Test pluck() uses the given key to index the results.
     */
    public function testPluckIndexesResultsByKey(): void
    {
        $items = [
            (object) ['id' => 1, 'name' => 'Ada'],
            (object) ['id' => 2, 'name' => 'Grace'],
        ];

        $this->assertSame(
            [1 => 'Ada', 2 => 'Grace'],
            (new Collection($items))->pluck('name', 'id')->getAll()
        );
    }

    /**
     * Test pluck() resolves nested keys through dot notation.
     */
    public function testPluckResolvesNestedKeys(): void
    {
        $items = [
            ['user' => ['name' => 'Ada']],
            ['user' => ['name' => 'Grace']],
        ];

        $this->assertSame(['Ada', 'Grace'], (new Collection($items))->pluck('user.name')->getAll());
    }

    /**
     * Test pluck() with a key parameter on an empty collection returns an empty result.
     */
    public function testPluckWithKeyOnEmptyCollectionReturnsEmpty(): void
    {
        $result = (new Collection([]))->pluck('name', 'id');

        $this->assertSame([], $result->getAll());
    }

    /**
     * Test pluck() indexes results using string keys when the key field is a string.
     */
    public function testPluckIndexesResultsByStringKey(): void
    {
        $items = [
            (object) ['role' => 'admin', 'name' => 'Ada'],
            (object) ['role' => 'editor', 'name' => 'Grace'],
        ];

        $this->assertSame(
            ['admin' => 'Ada', 'editor' => 'Grace'],
            (new Collection($items))->pluck('name', 'role')->getAll()
        );
    }

    /**
     * Test pluck() skips items where the key property does not exist.
     */
    public function testPluckSkipsItemsWithMissingKey(): void
    {
        $items = [
            (object) ['id' => 1, 'name' => 'Ada'],
            (object) ['name' => 'NoId'],
            (object) ['id' => 3, 'name' => 'Grace'],
        ];

        $this->assertSame(
            [1 => 'Ada', 3 => 'Grace'],
            (new Collection($items))->pluck('name', 'id')->getAll()
        );
    }

    /**
     * Test pluck() returns an empty collection when no items have the key property.
     */
    public function testPluckReturnsEmptyWhenAllKeysMissing(): void
    {
        $items = [
            (object) ['name' => 'Ada'],
            (object) ['name' => 'Grace'],
        ];

        $this->assertSame([], (new Collection($items))->pluck('name', 'id')->getAll());
    }

    /**
     * Test pluck() mixes string-keyed and missing-key items.
     */
    public function testPluckMixesStringKeysAndMissingKeys(): void
    {
        $items = [
            (object) ['role' => 'admin', 'name' => 'Ada'],
            (object) ['name' => 'NoRole'],
            (object) ['role' => 'editor', 'name' => 'Grace'],
        ];

        $this->assertSame(
            ['admin' => 'Ada', 'editor' => 'Grace'],
            (new Collection($items))->pluck('name', 'role')->getAll()
        );
    }

    /**
     * Test pluck() with a single item whose key property is missing.
     */
    public function testPluckSingleItemMissingKey(): void
    {
        $items = [
            (object) ['name' => 'Ada'],
        ];

        $this->assertSame([], (new Collection($items))->pluck('name', 'id')->getAll());
    }

    /**
     * Test slice() returns a new collection of the requested range.
     */
    public function testSliceReturnsRequestedRange(): void
    {
        $result = (new Collection(['a', 'b', 'c', 'd']))->slice(1, 2);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame([0 => 'b', 1 => 'c'], $result->getAll());
        $this->assertSame(['a', 'b', 'c', 'd'], (new Collection(['a', 'b', 'c', 'd']))->getAll());
    }

    /**
     * Test toArray() converts AbstractModel items and keeps plain values.
     */
    public function testToArrayConvertsModelsAndKeepsPlainValues(): void
    {
        $model      = new FakeModel(['name' => 'Ada', 'score' => 9]);
        $collection = new Collection([$model, 'plain']);

        $this->assertSame(
            [0 => ['name' => 'Ada', 'score' => 9], 1 => 'plain'],
            $collection->toArray()
        );
    }

    /**
     * Test toArray() preserves associative keys while converting models.
     */
    public function testToArrayPreservesAssociativeKeys(): void
    {
        $model      = new FakeModel(['name' => 'Ada']);
        $collection = new Collection(['first' => $model, 'second' => 'plain']);

        $this->assertSame(
            ['first' => ['name' => 'Ada'], 'second' => 'plain'],
            $collection->toArray()
        );
    }

    /**
     * Test getAll() returns the raw underlying items.
     */
    public function testGetAllReturnsRawItems(): void
    {
        $items = ['a' => 1, 'b' => 2];

        $this->assertSame($items, (new Collection($items))->getAll());
    }

    /**
     * Test filter() with a callback keeps matching items and preserves keys.
     */
    public function testFilterWithCallbackKeepsKeys(): void
    {
        $result = (new Collection([1, 2, 3, 4]))->filter(fn ($item) => $item > 2);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame([2 => 3, 3 => 4], $result->getAll());
    }

    /**
     * Test filter() without a callback removes falsy values.
     */
    public function testFilterRemovesFalsyValuesWithoutCallback(): void
    {
        $result = (new Collection([0, 1, '', 'x', null]))->filter();

        $this->assertSame([1 => 1, 3 => 'x'], $result->getAll());
    }

    /**
     * Test unique() drops duplicate values for the given key.
     */
    public function testUniqueDropsDuplicatesForKey(): void
    {
        $items = [
            (object) ['id' => 1, 'name' => 'Ada'],
            (object) ['id' => 1, 'name' => 'Ada'],
            (object) ['id' => 2, 'name' => 'Grace'],
        ];

        $result = (new Collection($items))->unique('id');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertSame('Ada', $result->getAll()[0]->name);
        $this->assertSame('Grace', $result->getAll()[1]->name);
    }

    /**
     * Test unique() resolves uniqueness through nested dot-notated keys.
     */
    public function testUniqueWithNestedKey(): void
    {
        $items = [
            (object) ['user' => (object) ['id' => 1]],
            (object) ['user' => (object) ['id' => 1]],
            (object) ['user' => (object) ['id' => 2]],
        ];

        $result = (new Collection($items))->unique('user.id');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertSame(1, $result->getAll()[0]->user->id);
        $this->assertSame(2, $result->getAll()[1]->user->id);
    }

    /**
     * Test where() returns matching objects using strict comparison.
     */
    public function testWhereReturnsMatchingObjectsStrictly(): void
    {
        $admin    = (object) ['role' => 'admin', 'name' => 'Ada'];
        $editor   = (object) ['role' => 'editor', 'name' => 'Grace'];
        $admin2   = (object) ['role' => 'admin', 'name' => 'Linus'];

        $result = (new Collection([$admin, $editor, $admin2]))->where('role', 'admin');

        $this->assertSame([$admin, $admin2], $result);
        $this->assertIsArray($result);
    }

    /**
     * Test where() skips items that do not carry the given property.
     */
    public function testWhereIgnoresItemsWithoutProperty(): void
    {
        $admin  = (object) ['role' => 'admin', 'name' => 'Ada'];
        $editor = (object) ['name' => 'Grace'];

        $result = (new Collection([$admin, $editor]))->where('role', 'admin');

        $this->assertSame([$admin], $result);
    }

    /**
     * Test firstWhere() returns the first matching object or null.
     */
    public function testFirstWhereReturnsFirstMatchOrNull(): void
    {
        $ada  = (object) ['role' => 'admin', 'name' => 'Ada'];
        $linus = (object) ['role' => 'admin', 'name' => 'Linus'];

        $this->assertSame($ada, (new Collection([$ada, $linus]))->firstWhere('role', 'admin'));
        $this->assertNull((new Collection([$ada]))->firstWhere('role', 'editor'));
    }

    /**
     * Test isEmpty() reflects whether the collection has items.
     */
    public function testIsEmpty(): void
    {
        $this->assertTrue((new Collection())->isEmpty());
        $this->assertTrue((new Collection([]))->isEmpty());
        $this->assertFalse((new Collection([1]))->isEmpty());
    }

    /**
     * Test first() returns the first item and null for an empty collection.
     */
    public function testFirstReturnsFirstItemOrNull(): void
    {
        $this->assertSame('a', (new Collection(['a', 'b']))->first());
        $this->assertNull((new Collection())->first());
        $this->assertNull((new Collection(['x' => 'a']))->first());
    }

    /**
     * Test contains() detects a matching object property.
     */
    public function testContains(): void
    {
        $items = [
            (object) ['role' => 'admin'],
            (object) ['role' => 'editor'],
        ];

        $this->assertTrue((new Collection($items))->contains('role', 'admin'));
        $this->assertFalse((new Collection($items))->contains('role', 'guest'));
    }

    /**
     * Test contains() skips items that do not carry the given property.
     */
    public function testContainsIgnoresItemsWithoutProperty(): void
    {
        $items = [
            (object) ['name' => 'Ada'],
            (object) ['role' => 'admin'],
        ];

        $this->assertTrue((new Collection($items))->contains('role', 'admin'));
    }

    /**
     * Test sum() totals numeric values extracted by key.
     */
    public function testSumByKeyTotalsNumericValues(): void
    {
        $items = [
            (object) ['price' => 10],
            (object) ['price' => '15'],
            (object) ['price' => 'nope'],
        ];

        $this->assertSame(25, (new Collection($items))->sum('price'));
    }

    /**
     * Test sum() accepts a callable.
     */
    public function testSumWithCallable(): void
    {
        $result = (new Collection([1, 2, 3]))->sum(fn ($item) => $item * 2);

        $this->assertSame(12, $result);
    }

    /**
     * Test sum() over an empty collection returns zero.
     */
    public function testSumWithEmptyCollection(): void
    {
        $this->assertSame(0, (new Collection())->sum('price'));
    }

    /**
     * Test push() appends items and returns the same instance.
     */
    public function testPushAppendsItems(): void
    {
        $collection = new Collection(['a']);

        $result = $collection->push('b', 'c');

        $this->assertSame($collection, $result);
        $this->assertSame(['a', 'b', 'c'], $collection->getAll());
    }

    /**
     * Test merge() combines an array into the collection.
     */
    public function testMergeWithArray(): void
    {
        $collection = new Collection(['a' => 1]);

        $result = $collection->merge(['b' => 2]);

        $this->assertSame($collection, $result);
        $this->assertSame(['a' => 1, 'b' => 2], $collection->getAll());
    }

    /**
     * Test merge() combines another collection.
     */
    public function testMergeWithCollection(): void
    {
        $collection = new Collection(['a' => 1]);

        $collection->merge(new Collection(['b' => 2]));

        $this->assertSame(['a' => 1, 'b' => 2], $collection->getAll());
    }

    /**
     * Test dataGet() resolves nested array keys and returns the default.
     */
    public function testDataGetFromArray(): void
    {
        $collection = new Collection();
        $target     = ['user' => ['name' => 'Ada']];

        $this->assertSame('Ada', $collection->dataGet($target, 'user.name'));
        $this->assertSame('fallback', $collection->dataGet($target, 'user.missing', 'fallback'));
        $this->assertNull($collection->dataGet($target, 'user.missing'));
    }

    /**
     * Test dataGet() resolves object properties through dot notation.
     */
    public function testDataGetFromObject(): void
    {
        $collection = new Collection();
        $target     = (object) ['user' => (object) ['name' => 'Ada']];

        $this->assertSame('Ada', $collection->dataGet($target, 'user.name'));
        $this->assertSame('fallback', $collection->dataGet($target, 'user.missing', 'fallback'));
    }

    /**
     * Test dataGet() resolves ArrayAccess targets.
     */
    public function testDataGetFromArrayAccess(): void
    {
        $collection = new Collection();

        $this->assertSame('Ada', $collection->dataGet(new ArrayObject(['name' => 'Ada']), 'name'));
        $this->assertSame('fallback', $collection->dataGet(new ArrayObject(['name' => 'Ada']), 'missing', 'fallback'));
    }

    /**
     * Test dataGet() with a null key returns the target unchanged.
     */
    public function testDataGetWithNullKeyReturnsTarget(): void
    {
        $target = ['name' => 'Ada'];

        $this->assertSame($target, (new Collection())->dataGet($target, null));
    }

    /**
     * Test dataGet() returns the default when the target is a scalar.
     */
    public function testDataGetReturnsDefaultForScalarTarget(): void
    {
        $collection = new Collection();

        $this->assertSame('fallback', $collection->dataGet('plain-string', 'user.name', 'fallback'));
        $this->assertNull($collection->dataGet(42, 'user.name'));
    }

    /**
     * Test dataGet() traverses a null-valued array segment and returns the default.
     */
    public function testDataGetTraversesNullValuedArraySegment(): void
    {
        $collection = new Collection();
        $target     = ['user' => null];

        $this->assertSame('fallback', $collection->dataGet($target, 'user.name', 'fallback'));
    }

    /**
     * Test sortByDesc() sorts objects in descending order.
     */
    public function testSortByDescSortsObjectsDescending(): void
    {
        $items = [
            (object) ['name' => 'low', 'score' => 1],
            (object) ['name' => 'high', 'score' => 3],
            (object) ['name' => 'mid', 'score' => 2],
        ];

        $result = (new Collection($items))->sortByDesc('score');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame(['high', 'mid', 'low'], array_column($result->getAll(), 'name'));
    }

    /**
     * Test the collection is countable.
     */
    public function testCountable(): void
    {
        $collection = new Collection([1, 2, 3]);

        $this->assertSame(3, count($collection));
        $this->assertSame(3, $collection->count());
    }

    /**
     * Test the collection is iterable.
     */
    public function testIteratorAggregate(): void
    {
        $visited = [];

        foreach (new Collection(['a' => 1, 'b' => 2]) as $key => $item) {
            $visited[$key] = $item;
        }

        $this->assertSame(['a' => 1, 'b' => 2], $visited);
    }

    /**
     * Test array access read, write, existence and removal.
     */
    public function testArrayAccess(): void
    {
        $collection = new Collection(['a' => 1]);

        $this->assertTrue(isset($collection['a']));
        $this->assertFalse(isset($collection['b']));
        $this->assertSame(1, $collection['a']);

        $collection['b'] = 2;
        $this->assertSame(2, $collection['b']);

        unset($collection['b']);
        $this->assertFalse(isset($collection['b']));

        $collection[] = 3;
        $this->assertSame(3, $collection->getAll()[0]);
    }
}
