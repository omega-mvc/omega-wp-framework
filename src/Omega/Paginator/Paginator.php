<?php

/**
 * Part of Omega - Paginator Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html    GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Paginator;

use Omega\Collection\Collection;

use function array_key_exists;
use function array_merge;
use function ceil;
use function is_int;
use function max;

/**
 * Paginator
 *
 * Provides a lightweight pagination layer over a collection of items.
 *
 * It calculates pagination metadata such as total items, current page,
 * last page, and items per page, and wraps the current slice of data
 * into a structured response format suitable for APIs or JSON resources.
 *
 * The class is designed to be framework-agnostic and works directly
 * with Collection instances, while supporting optional configuration
 * overrides via the options array.
 *
 * @category  Omega
 * @package   Paginator
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html    GPL V3.0+
 * @version   1.0.0
 */
class Paginator
{
    #region Properties
    /** @var int Total number of items before pagination is applied. */
    protected int $total;

    /** @var int Last available page number based on total and per-page value. */
    protected int $lastPage;

    /** @var int Number of items to display per page. */
    protected int $perPage;

    /** @var int Current page number resolved for the request. */
    protected int $currentPage;

    /** @var Collection Collection of items for the current page. */
    protected Collection $items;

    /** @var array<string, mixed> Additional pagination configuration options. */
    protected array $options = [];
    #endregion

    #region Lifecycle
    /**
     * Paginator constructor.
     *
     * Initializes pagination state and calculates derived values such as
     * last page and validated current page number.
     *
     * @param mixed     $items       Items for the current page
     * @param int       $total       Total number of items
     * @param int       $perPage     Items per page
     * @param int|null  $currentPage Current page number (optional)
     * @param array<string, mixed> $options Extra configuration options
     */
    public function __construct(
        mixed $items,
        int $total,
        int $perPage,
        ?int $currentPage = null,
        array $options = []
    ) {
        $this->options = $options;
        $this->total = self::resolveIntOption($options, 'total', $total);
        $this->perPage = self::resolveIntOption($options, 'perPage', $perPage);
        $this->lastPage = max((int) ceil($this->total / $this->perPage), 1);
        $this->currentPage = $this->setCurrentPage(self::resolveIntOption($options, 'currentPage', $currentPage));
        $this->items = $items instanceof Collection ? $items : new Collection((array) $items);
    }
    #endregion

    #region Helpers
    /**
     * Resolve an integer option value from the options array, falling back to a default.
     *
     * @param array<string, mixed> $options The options array to search
     * @param string $key The option key
     * @param int|null $fallback The default value if the option is not an integer
     */
    private static function resolveIntOption(array $options, string $key, ?int $fallback): int
    {
        if (array_key_exists($key, $options)) {
            $value = $options[$key];

            if (is_int($value)) {
                return $value;
            }

            if ($value === null) {
                return 1;
            }

            if ($fallback !== null) {
                return $fallback;
            }

            return 1;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        return 1;
    }
    #endregion

    #region Validation
    /**
     * Resolve and validate the current page number.
     *
     * Ensures the page is a valid integer and falls back to page 1
     * if the provided value is invalid or omitted.
     *
     * @param int|null $currentPage Requested page number, or null to use page 1
     * @return int Validated page number
     */
    protected function setCurrentPage(?int $currentPage): int
    {
        if ($currentPage === null) {
            return 1;
        }

        return $this->isValidPageNumber($currentPage) ? $currentPage : 1;
    }

    /**
     * Determine whether a page number is valid.
     *
     * A valid page must be a positive integer greater than or equal to 1.
     *
     * @param int $page Page number to validate
     * @return bool True if valid, false otherwise
     */
    protected function isValidPageNumber(int $page): bool
    {
        return $page >= 1;
    }
    #endregion

    #region Data
    /**
     * Get the underlying collection of paginated items.
     *
     * @return Collection Current page items
     */
    public function getCollection(): Collection
    {
        return $this->items;
    }
    #endregion

    #region Metadata
    /**
     * Get pagination metadata attributes.
     *
     * @return array{
     *     total:int,
     *     per_page:int,
     *     current_page:int,
     *     last_page:int
     * }
     */
    public function getAttributes(): array
    {
        return [
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
        ];
    }
    #endregion

    #region Serialization
    /**
     * Convert the paginator instance into an array structure.
     *
     * Merges pagination metadata with the current page items.
     *
     * @return array{total:int, per_page:int, current_page:int, last_page:int, data:array<int|string, mixed>}
     */
    public function toArray(): array
    {
        return array_merge(
            $this->getAttributes(),
            [
                'data' => $this->items->toArray(),
            ]
        );
    }
    #endregion
}
