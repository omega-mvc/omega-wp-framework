<?php

/**
 * Part of Omega - Collection Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Collection;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Omega\Database\ORM\AbstractModel;
use ReturnTypeWillChange;

use function array_any;
use function array_combine;
use function array_find;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_push;
use function array_reduce;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_callable;
use function is_null;
use function is_numeric;
use function is_object;
use function iterator_apply;
use function usort;

/**
 * Lightweight Collection implementation for the Omega framework.
 *
 * This class provides a fluent, iterable wrapper around a native PHP array,
 * designed primarily for WordPress-oriented data handling (e.g. WP_Post,
 * WP_Query results, Eloquent-like models, and generic arrays/objects).
 *
 * It offers utility methods for transforming, filtering, and querying data
 * while maintaining compatibility with core PHP interfaces such as
 * ArrayAccess, IteratorAggregate, and Countable.
 *
 * The Collection is intentionally flexible and does not enforce strict typing
 * on stored items, allowing it to operate seamlessly across WordPress core
 * structures and custom application models.
 *
 * Key features include:
 * - Fluent transformation methods (map, filter, merge, pluck, etc.)
 * - Safe array/object access utilities (dataGet-style resolution)
 * - Compatibility with PHP native constructs (count(), foreach, array access)
 * - Support for both arrays and object-based datasets
 * - Integration-friendly design for WordPress plugin architecture
 *
 * This class is designed to act as a bridge between WordPress's loosely typed
 * data structures and a more expressive, chainable collection API.
 *
 * @category  Omega
 * @package   Collection
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    #region Properties
    /**
     * Collection constructor.
     *
     * Initializes the collection with the given items array.
     * Each item can be of any type, typically array or object depending on usage context.
     *
     * @param array<int|string, mixed> $items Initial set of items to store in the collection.
     */
    public function __construct(public array $items = [])
    {
    }
    #endregion

    #region Transformation
    /**
     * Iterate over all items in the collection and execute the given callback for each element.
     *
     * The callback receives the current item and its key as arguments.
     * If the callback returns `false`, the iteration is immediately stopped.
     *
     * This method operates on the collection in-place and always returns the same instance,
     * allowing fluent chaining.
     *
     * @param callable(mixed, int|string): (bool|void) $callback A function that receives the current item and its key.
     *                                                           Returning false will break the iteration early.
     * @return static The same collection instance, for fluent chaining.
     */
    public function each(callable $callback): static
    {
        $iterator = $this->getIterator();

        iterator_apply($iterator, fn () => $callback($iterator->current(), $iterator->key()) !== false);

        return $this;
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param callable $callback A function applied to each item and its key
     * @return array The transformed items as a plain array
     */
    public function map(callable $callback): array
    {
        $keys  = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return array_combine($keys, $items);
    }

    /**
     * Extract values from the collection for a given key, optionally using another key for indexing.
     *
     * @param string|int $value The key to extract values from each item
     * @param string|int|null $key The key to use for indexing the resulting array, or null for numeric indexing
     * @return Collection A new collection containing the extracted values
     */
    public function pluck(string|int $value, string|int|null $key = null): Collection
    {
        $values = array_map(
            fn (mixed $item): mixed => $this->dataGet($item, $value),
            $this->items
        );

        if (is_null($key)) {
            return new self(array_values($values));
        }

        $keys = array_map(
            fn (mixed $item): mixed => $this->dataGet($item, $key),
            $this->items
        );

        return new self(array_combine($keys, $values));
    }

    /**
     * Return a slice of the collection starting at the given offset.
     *
     * @param int $offset The starting offset for the slice
     * @param int|null $length The number of items to include, or null for no limit
     * @return Collection A new collection instance containing the sliced items
     */
    public function slice(int $offset, ?int $length = null): Collection
    {
        $slicedArray = array_slice($this->items, $offset, $length);

        return new self($slicedArray);
    }

    /**
     * Convert the collection into a plain array, recursively converting models when needed.
     *
     * @return array The collection represented as a plain PHP array
     */
    public function toArray(): array
    {
        return array_map(
            fn (mixed $item): mixed => $item instanceof AbstractModel ? $item->toArray() : $item,
            $this->items
        );
    }

    /**
     * Return the raw underlying items of the collection without transformation.
     *
     * @return array The raw items stored in the collection
     */
    public function getAll(): array
    {
        return $this->items;
    }
    #endregion

    #region Filtering
    /**
     * Filter the collection using an optional callback.
     *
     * @param callable|null $callback The filtering condition, or null to remove falsy values
     * @return Collection A new collection containing only the filtered items
     */
    public function filter(?callable $callback = null): Collection
    {
        $items = array_filter($this->items, $callback);

        return new self($items);
    }

    /**
     * Return a collection with duplicate values removed based on the given key.
     *
     * @param string|int $key The key used to determine uniqueness for each item
     * @return Collection A new collection containing only unique items
     */
    public function unique(string|int $key): Collection
    {
        $seenKeys = [];

        $uniqueItems = array_filter(
            $this->items,
            function (mixed $item) use ($key, &$seenKeys): bool {
                $itemKey = $this->dataGet($item, $key);

                if (in_array($itemKey, $seenKeys, true)) {
                    return false;
                }

                $seenKeys[] = $itemKey;

                return true;
            }
        );

        return new self(array_values($uniqueItems));
    }

    /**
     * Filter the collection and return all items matching the given key/value pair.
     *
     * @param string|int $key The property or array key to compare against
     * @param mixed $value The value to match strictly against each item
     * @return array A filtered array of items matching the condition
     */
    public function where(string|int $key, mixed $value): array
    {
        return array_values(array_filter(
            $this->items,
            fn (mixed $item): bool => isset($item->$key) && $item->$key === $value
        ));
    }

    /**
     * Retrieve the first item in the collection where the given property
     * matches the specified value.
     *
     * This method is intended for collections of objects and returns the first
     * matching item using strict comparison (===). If no matching item is found,
     * null is returned.
     *
     * @param string|int $key The property name (or array key) to inspect on each item.
     * @param mixed $value The value to compare against using strict equality.
     *
     * @return mixed|null The first matching item, or null if no match is found.
     */
    public function firstWhere(string|int $key, mixed $value): mixed
    {
        return array_find(
            $this->items,
            fn (mixed $item): bool => $item->$key === $value
        );
    }

    /**
     * Determine whether the collection is empty.
     *
     * @return bool True if the collection contains no items, false otherwise
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Return the first item in the collection without applying any filter.
     *
     * @return mixed|null The first item in the collection, or null if the collection is empty
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Determine whether the collection contains at least one item
     * where the given property matches the specified value.
     *
     * This method is primarily designed for collections of objects
     * (e.g. WP_Post, stdClass, or custom models) and performs a strict
     * comparison (===) on the given property.
     *
     * @param string|int $key The property name (or array key) to check on each item.
     * @param mixed $value The value to compare against using strict equality.
     * @return bool True if at least one item matches the condition, false otherwise.
     */
    public function contains(string|int $key, mixed $value): bool
    {
        return array_any(
            $this->items,
            fn (mixed $item): bool => isset($item->$key) && $item->$key === $value
        );
    }
    #endregion

    #region Aggregation
    /**
     * Calculate the sum of a numeric field or computed value from the collection.
     *
     * @param callable|string $key A callback returning the value to sum, or a key to extract from each item
     * @return float|int|string The computed sum of all numeric values in the collection
     */
    public function sum(callable|string $key): float|int|string
    {
        return array_reduce(
            $this->items,
            function (float|int|string $total, mixed $item) use ($key): float|int|string {
                $value = is_callable($key) ? $key($item) : $this->dataGet($item, $key);

                return is_numeric($value) ? $total + $value : $total;
            },
            0
        );
    }
    #endregion

    #region Mutation
    /**
     * Push one or more items onto the end of the collection.
     *
     * @param mixed ...$values Items to append to the collection.
     * @return static
     */
    public function push(mixed ...$values): static
    {
        array_push($this->items, ...$values);

        return $this;
    }

    /**
     * Merge the collection with another collection or array of items.
     *
     * @param array|Collection $collection The items to merge into the collection
     * @return static The updated collection instance
     */
    public function merge(array|Collection $collection): static
    {
        $this->items = array_merge(
            $this->items,
            $collection instanceof Collection
                ? $collection->getAll()
                : $collection
        );

        return $this;
    }

    #endregion

    #region Access
    /**
     * Retrieve a value from a nested array or object using "dot" notation.
     *
     * @param mixed $target The array or object to search within
     * @param string|null $key The dot-notated key path to retrieve
     * @param mixed $default The default value returned if the key does not exist
     * @return mixed The resolved value or the default value if not found
     */
    public function dataGet(mixed $target, ?string $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $target;
        }

        return array_reduce(
            explode('.', $key),
            function (mixed $carry, string $segment) use ($default): mixed {
                if (is_array($carry)) {
                    return array_key_exists($segment, $carry) ? $carry[$segment] : $default;
                }

                if ($carry instanceof ArrayAccess) {
                    return isset($carry[$segment]) ? $carry[$segment] : $default;
                }

                if (is_object($carry)) {
                    return isset($carry->{$segment}) ? $carry->{$segment} : $default;
                }

                return $default;
            },
            $target
        );
    }
    #endregion

    #region Sorting
    /**
     * Sort the collection in descending order based on the given key.
     *
     * @param string|int $key The property or array key used for sorting
     * @return Collection A new collection instance sorted in descending order
     */
    public function sortByDesc(string|int $key): Collection
    {
        $items = $this->items;

        usort($items, function ($a, $b) use ($key) {
            return $b->$key <=> $a->$key;
        });

        return new self($items);
    }
    #endregion

    #region ArrayAccess
    /**
     * Determine whether an item exists at the given offset.
     *
     * @param mixed $offset The array offset to check
     * @return bool True if the offset exists, false otherwise
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Retrieve an item at the specified offset.
     *
     * @param mixed $offset The array offset to retrieve
     * @return mixed The item at the given offset or null if not set
     */
    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * Set an item at the specified offset.
     *
     * @param mixed $offset The array offset to assign, or null to append
     * @param mixed $value The value to store at the given offset
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Remove an item at the specified offset.
     *
     * @param mixed $offset The array offset to remove
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
    #endregion

    #region Countable
    /**
     * Count the number of items in the collection.
     *
     * @return int Return the number of items in collection.
     */
    public function count(): int
    {
        return count($this->items);
    }
    #endregion

    #region Iterator
    /**
     * Get an iterator for traversing the collection items.
     *
     * @return ArrayIterator An iterator for the collection items
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
    #endregion
}
