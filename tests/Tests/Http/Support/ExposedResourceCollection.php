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

namespace Tests\Http\Support;

use Omega\Database\ORM\AbstractModel;
use Omega\Http\Json\ResourceCollection;

/**
 * ResourceCollection variant that exposes the protected resource property.
 *
 * The base class never assigns its protected resource, so this subclass
 * allows tests to exercise the magic property accessor.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class ExposedResourceCollection extends ResourceCollection
{
    /**
     * Assign the resource used by the magic accessor.
     *
     * @param AbstractModel $resource Model instance to expose
     * @return void
     */
    public function setResource(AbstractModel $resource): void
    {
        $this->resource = $resource;
    }
}
