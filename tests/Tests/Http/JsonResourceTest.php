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
use Omega\Http\Exceptions\ResourceMethodNotFoundException;
use Omega\Http\Json\JsonResource;
use Omega\Http\Json\ResourceCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Http\Support\UserResource;

/**
 * Tests the JsonResource transformation layer.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(JsonResource::class)]
#[CoversClass(ResourceMethodNotFoundException::class)]
final class JsonResourceTest extends HttpTestCase
{
    /**
     * Test the base transformer returns an empty array.
     */
    public function testBaseToArrayIsEmpty(): void
    {
        $resource = new JsonResource($this->makeModel(['id' => 1]));

        $this->assertSame([], $resource->toArray());
    }

    /**
     * Test a subclass transforms the model into an array.
     */
    public function testSubclassTransformsTheModelIntoAnArray(): void
    {
        $resource = new UserResource($this->makeModel(['id' => 1, 'name' => 'Ada']));

        $this->assertSame(['id' => 1, 'name' => 'Ada'], $resource->toArray());
    }

    /**
     * Test the magic getter returns existing attributes.
     */
    public function testMagicGetReturnsExistingAttribute(): void
    {
        $resource = new UserResource($this->makeModel(['id' => 1, 'name' => 'Ada']));

        $this->assertSame(1, $resource->id);
        $this->assertSame('Ada', $resource->name);
    }

    /**
     * Test the magic getter returns null for a missing attribute.
     */
    public function testMagicGetReturnsNullForMissingAttribute(): void
    {
        $resource = new UserResource($this->makeModel(['id' => 1]));

        $this->assertNull($resource->missing);
    }

    /**
     * Test the magic caller proxies to resource methods.
     */
    public function testMagicCallProxiesToResourceMethod(): void
    {
        $resource = new UserResource($this->makeModel(['id' => 1, 'name' => 'Ada']));

        $this->assertTrue($resource->keyExists('name'));
    }

    /**
     * Test the magic caller throws for unknown methods.
     */
    public function testMagicCallThrowsForUnknownMethod(): void
    {
        $resource = new UserResource($this->makeModel(['id' => 1]));

        $this->expectException(ResourceMethodNotFoundException::class);
        $this->expectExceptionMessage(
            'Method notExisting does not exist on ' . UserResource::class . ' or its resource.'
        );

        $resource->notExisting();
    }

    /**
     * Test the collection factory wraps models into a resource collection.
     */
    public function testCollectionFactoryWrapsModels(): void
    {
        $collection = new Collection([
            $this->makeModel(['id' => 1, 'name' => 'Ada']),
            $this->makeModel(['id' => 2, 'name' => 'Bob']),
        ]);

        $result = UserResource::collection($collection);

        $this->assertInstanceOf(ResourceCollection::class, $result);
        $this->assertSame(UserResource::class, $result->collects);
        $this->assertSame([
            ['id' => 1, 'name' => 'Ada'],
            ['id' => 2, 'name' => 'Bob'],
        ], $result->collection());
    }

    /**
     * Test the exception accepts a plain message.
     */
    public function testExceptionAcceptsPlainMessage(): void
    {
        $exception = new ResourceMethodNotFoundException('No such method.');

        $this->assertSame('No such method.', $exception->getMessage());
    }

    /**
     * Test the exception formats a sprintf-style message with parameters.
     */
    public function testExceptionFormatsMessageWithParameters(): void
    {
        $exception = new ResourceMethodNotFoundException(
            'Method %s does not exist on %s.',
            'save',
            'App\\Http\\Json\\JsonResource'
        );

        $this->assertSame(
            'Method save does not exist on App\\Http\\Json\\JsonResource.',
            $exception->getMessage()
        );
    }
}
