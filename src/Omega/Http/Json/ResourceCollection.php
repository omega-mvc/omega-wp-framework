<?php

/**
 * Part of Omega - Http Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Http\Json;

use Omega\Collection\Collection;
use Omega\Database\ORM\AbstractModel;
use Omega\Paginator\Paginator;

use function array_map;
use function array_merge;
use function is_bool;

/**
 * ResourceCollection
 *
 * Wraps a collection of models into resource representations with optional
 * transformation, metadata handling, and pagination support.
 *
 * Provides a consistent JSON-ready structure for API responses while
 * allowing per-item resource transformation and optional meta merging.
 *
 * @category   Omega
 * @package    Http
 * @subpackage Json
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class ResourceCollection
{
    #region Properties
    /** @var string|null Fully qualified resource class used to transform items. */
    public ?string $collects = null;

    /** @var Collection Underlying collection of items to be transformed. */
    public Collection $collection;

    /** @var bool Merge meta into root level instead of nesting under "meta". */
    public bool $mergeMeta = false;

    /** @var array Extra metadata attached to the collection response. */
    protected array $meta = [];

    /** @var AbstractModel Model instance used for dynamic attribute resolution. */
    protected AbstractModel $resource;
    #endregion

    #region Lifecycle
    /**
     * Create a new ResourceCollection instance.
     *
     * Supports raw collections or paginated results and optionally applies
     * a resource transformer class for each item.
     *
     * @param Collection|Paginator $collection Source data collection or paginator.
     * @param string|null $collects Optional resource class for item transformation.
     * @param array $options Configuration options (e.g. meta merging).
     */
    public function __construct(Collection $collection, ?string $collects = null, array $options = [])
    {
        if ($collects) {
            $this->collects = $collects;
        }

        $this->collection = $collection;

        if (isset($options['mergeMeta'])) {
            if (is_bool($options['mergeMeta'])) {
                $this->mergeMeta = $options['mergeMeta'];
            }
        }
    }

    /**
     * Create a ResourceCollection from a Paginator instance.
     *
     * @param Paginator $paginator Source paginator to extract collection and meta from.
     * @param string|null $collects Optional resource class for item transformation.
     * @param array $options Configuration options (e.g. meta merging).
     */
    public static function fromPaginator(Paginator $paginator, ?string $collects = null, array $options = []): static
    {
        $options['mergeMeta'] = $options['mergeMeta'] ?? true;

        $instance = new static($paginator->getCollection(), $collects, $options);
        $instance->meta = $paginator->getAttributes();

        return $instance;
    }
    #endregion

    #region Transformation
    /**
     * Transform the underlying collection into an array of resources.
     *
     * If a resource class is defined, each item is transformed individually.
     * Otherwise, raw collection data is returned.
     *
     * @return array Transformed collection data.
     */
    public function collection(): array
    {
        if ($this->collects) {
            $resourceClass = $this->collects;
            $resources = array_map(
                static fn($item): array => (new $resourceClass($item))->toArray(),
                $this->collection->getAll()
            );

            return $resources;
        }

        return $this->collection->toArray();
    }
    #endregion

    #region Metadata
    /**
     * Retrieve metadata associated with the collection.
     *
     * @return array Collection metadata.
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Append metadata to the given payload.
     *
     * Depending on configuration, metadata is either merged into the root
     * structure or nested under a "meta" key.
     *
     * @param array $data Base response payload.
     * @return array Payload enriched with metadata.
     */
    public function appendMeta(array $data): array
    {
        if ($this->meta === []) {
            return $data;
        }

        if ($this->mergeMeta) {
            return array_merge($data, $this->meta);
        }

        $data['meta'] = $this->meta;

        return $data;
    }
    #endregion

    #region Serialization
    /**
     * Convert the resource collection into a JSON-ready array structure.
     *
     * Includes transformed data and optional metadata.
     *
     * @return array Final serialized representation of the collection.
     */
    public function toArray(): array
    {
        return $this->appendMeta([
            'data' => $this->collection(),
        ]);
    }
    #endregion

    #region Magic Access
    /**
     * Dynamically access attributes from the underlying model resource.
     *
     * Delegates property access to the associated AbstractModel instance
     * if the requested key exists.
     *
     * @param string $name Attribute name.
     * @return mixed|null Attribute value or null if not found.
     */
    public function __get(string $name)
    {
        if ($this->resource->keyExists($name)) {
            return $this->resource[$name];
        }

        return null;
    }
    #endregion
}
