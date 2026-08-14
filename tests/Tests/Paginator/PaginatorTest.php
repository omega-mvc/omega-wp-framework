<?php

/**
 * Part of Omega - Tests Paginator Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Paginator;

use Omega\Collection\Collection;
use Omega\Paginator\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Paginator metadata and serialization behaviour.
 *
 * @category  Tests
 * @package   Paginator
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Paginator::class)]
final class PaginatorTest extends TestCase
{
    /**
     * Test the current page defaults to the first page.
     */
    public function testDefaultsToFirstPage(): void
    {
        $paginator = new Paginator(['a', 'b'], 25, 10);

        $this->assertSame(1, $paginator->getAttributes()['current_page']);
    }

    /**
     * Test an explicit current page is honored.
     */
    public function testHonorsExplicitCurrentPage(): void
    {
        $paginator = new Paginator(['c'], 25, 10, 3);

        $this->assertSame(3, $paginator->getAttributes()['current_page']);
    }

    /**
     * Test an invalid current page falls back to the first page.
     */
    public function testInvalidCurrentPageFallsBackToFirstPage(): void
    {
        $this->assertSame(1, (new Paginator([], 25, 10, 0))->getAttributes()['current_page']);
        $this->assertSame(1, (new Paginator([], 25, 10, -2))->getAttributes()['current_page']);
    }

    /**
     * Test the last page is computed with ceil.
     */
    public function testLastPageIsCeilOfTotalDividedByPerPage(): void
    {
        $this->assertSame(3, (new Paginator([], 25, 10))->getAttributes()['last_page']);
        $this->assertSame(1, (new Paginator([], 10, 10))->getAttributes()['last_page']);
        $this->assertSame(4, (new Paginator([], 40, 10))->getAttributes()['last_page']);
    }

    /**
     * Test the last page is at least one for an empty result set.
     */
    public function testLastPageIsAtLeastOneForEmptySet(): void
    {
        $this->assertSame(1, (new Paginator([], 0, 10))->getAttributes()['last_page']);
    }

    /**
     * Test getAttributes() exposes the full metadata structure.
     */
    public function testGetAttributes(): void
    {
        $paginator = new Paginator(['a', 'b'], 25, 10, 2);

        $this->assertSame(
            ['total' => 25, 'per_page' => 10, 'current_page' => 2, 'last_page' => 3],
            $paginator->getAttributes()
        );
    }

    /**
     * Test toArray() merges metadata with the current page items.
     */
    public function testToArrayMergesMetadataAndData(): void
    {
        $paginator = new Paginator(['a', 'b'], 4, 2, 1);

        $this->assertSame(
            ['total' => 4, 'per_page' => 2, 'current_page' => 1, 'last_page' => 2, 'data' => ['a', 'b']],
            $paginator->toArray()
        );
    }

    /**
     * Test plain array items are wrapped into a Collection.
     */
    public function testWrapsPlainArrayItemsIntoCollection(): void
    {
        $paginator = new Paginator(['a', 'b'], 2, 2, 1);

        $this->assertInstanceOf(Collection::class, $paginator->getCollection());
        $this->assertSame(['a', 'b'], $paginator->getCollection()->getAll());
    }

    /**
     * Test an existing Collection instance is preserved.
     */
    public function testKeepsCollectionInstance(): void
    {
        $items     = new Collection(['a']);
        $paginator = new Paginator($items, 1, 1, 1);

        $this->assertSame($items, $paginator->getCollection());
    }
}
