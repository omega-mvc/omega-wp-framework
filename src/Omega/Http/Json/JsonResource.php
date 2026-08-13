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
use Omega\Http\Exceptions\ResourceMethodNotFoundException;
use Omega\Paginator\Paginator;

use function call_user_func_array;
use function get_class;
use function method_exists;
use function sprintf;

/**
 * JsonResource
 *
 * Represents a single resource transformation layer responsible for converting
 * a domain model into an array structure suitable for JSON serialization.
 *
 * This class acts as a presentation wrapper around an underlying model,
 * allowing controlled exposure of data and transformation logic without
 * modifying the model itself.
 *
 * It also provides support for collections via the static collection() method
 * and transparently proxies property and method access to the underlying resource.
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
class JsonResource
{
    #region Lifecycle
    /**
     * Create a new resource instance.
     *
     * @param AbstractModel $resource The underlying model instance being transformed.
     * @param array $options Optional transformation options or metadata.
     */
    public function __construct(public AbstractModel $resource, public array $options = [])
    {
    }
    #endregion

    #region Transformation
    /**
     * Create a resource collection from a given dataset.
     *
     * Wraps a collection of models into a ResourceCollection instance using
     * the current resource class as transformer.
     *
     * @param Collection|Paginator $collection The collection of models to transform.
     * @param array $options Optional transformation options applied to the collection.
     * @return ResourceCollection The resource collection instance.
     */
    public static function collection(Collection|Paginator $collection, array $options = []): ResourceCollection
    {
        return new ResourceCollection($collection, static::class, $options);
    }

    /**
     * Transform the resource into an array.
     *
     * This method should be overridden in child classes to define the actual
     * transformation logic for the resource.
     *
     * @return array The transformed representation of the resource.
     */
    public function toArray(): array
    {
        return [];
    }
    #endregion

    #region Resource Proxy
    /**
     * Dynamically access properties on the underlying resource.
     *
     * This magic method allows transparent access to resource attributes
     * as if they were properties of the JsonResource instance.
     *
     * @param string $name The property name.
     * @return mixed|null The property value if it exists, null otherwise.
     */
    public function __get(string $name): mixed
    {
        if (isset($this->resource) && isset($this->resource[$name])) {
            return $this->resource[$name];
        }

        return null;
    }

    /**
     * Dynamically call methods on the underlying resource.
     *
     * This magic method proxies method calls to the wrapped resource instance
     * if the method exists.
     *
     * @param string $method The method name being called.
     * @param array $arguments The arguments passed to the method.
     * @return mixed The result of the proxied method call.
     * @throws ResourceMethodNotFoundException If the method does not exist
     *                                         on the resource or wrapper.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (isset($this->resource) && method_exists($this->resource, $method)) {
            return call_user_func_array([$this->resource, $method], $arguments);
        }

        throw new ResourceMethodNotFoundException(sprintf(
            'Method %s does not exist on %s or its resource.',
            $method,
            get_class($this)
        ));
    }
    #endregion
}
