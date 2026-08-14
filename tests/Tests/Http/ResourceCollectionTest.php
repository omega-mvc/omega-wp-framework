<?php

/**
 * Part of Omega - Tests Http Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Http;

use Omega\Collection\Collection;
use Omega\Http\Json\ResourceCollection;
use Omega\Paginator\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Http\Support\ExposedResourceCollection;
use Tests\Http\Support\UserResource;

/**
 * Tests the ResourceCollection wrapping and metadata handling.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ResourceCollection::class)]
final class ResourceCollectionTest extends HttpTestCase
{
    /**
     * Test a raw collection is returned unchanged without metadata.
     */
    public function testRawCollectionReturnsItems(): void
    {
        $collection = new ResourceCollection(new Collection([1, 2, 3]));

        $this->assertSame([1, 2, 3], $collection->collection());
        $this->assertSame([], $collection->getMeta());
    }

    /**
     * Test toArray() wraps raw items under a data key.
     */
    public function testToArrayWrapsDataKeyWithoutMeta(): void
    {
        $collection = new ResourceCollection(new Collection([1, 2]));

        $this->assertSame(['data' => [1, 2]], $collection->toArray());
    }

    /**
     * Test a collects class transforms each item.
     */
    public function testCollectsTransformsEachItem(): void
    {
        $collection = new ResourceCollection(
            new Collection([
                $this->makeModel(['id' => 1, 'name' => 'Ada']),
                $this->makeModel(['id' => 2, 'name' => 'Bob']),
            ]),
            UserResource::class
        );

        $this->assertSame([
            ['id' => 1, 'name' => 'Ada'],
            ['id' => 2, 'name' => 'Bob'],
        ], $collection->collection());
    }

    /**
     * Test a paginator input pulls metadata and merges it by default.
     */
    public function testPaginatorInputPullsMetaAndDefaultsToMergedMeta(): void
    {
        $paginator = new Paginator([1, 2, 3], 10, 2, 1);
        $collection = new ResourceCollection($paginator);

        $this->assertSame([
            'total'        => 10,
            'per_page'     => 2,
            'current_page' => 1,
            'last_page'    => 5,
        ], $collection->getMeta());
        $this->assertTrue($collection->mergeMeta);
        $this->assertSame([
            'data' => [1, 2, 3],
            'total' => 10,
            'per_page' => 2,
            'current_page' => 1,
            'last_page' => 5,
        ], $collection->toArray());
    }

    /**
     * Test the mergeMeta option nests metadata under a meta key.
     */
    public function testMergeMetaOptionNestsMetaUnderMetaKey(): void
    {
        $paginator = new Paginator([1, 2, 3], 10, 2, 1);
        $collection = new ResourceCollection($paginator, null, ['mergeMeta' => false]);

        $this->assertFalse($collection->mergeMeta);
        $this->assertSame([
            'data' => [1, 2, 3],
            'meta' => [
                'total'        => 10,
                'per_page'     => 2,
                'current_page' => 1,
                'last_page'    => 5,
            ],
        ], $collection->toArray());
    }

    /**
     * Test appendMeta() is a no-op when metadata is empty.
     */
    public function testAppendMetaIsNoopWhenMetaIsEmpty(): void
    {
        $collection = new ResourceCollection(new Collection([1]));

        $this->assertSame(['data' => [1]], $collection->appendMeta(['data' => [1]]));
    }

    /**
     * Test the magic getter delegates to the exposed resource model.
     */
    public function testMagicGetDelegatesToExposedResource(): void
    {
        $collection = new ExposedResourceCollection(new Collection([]));
        $collection->setResource($this->makeModel(['id' => 9, 'name' => 'Ada']));

        $this->assertSame('Ada', $collection->name);
        $this->assertNull($collection->missing);
    }
}
