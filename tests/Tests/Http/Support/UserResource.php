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

use Omega\Http\Json\JsonResource;

/**
 * Resource transformer used to observe JsonResource behaviour.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class UserResource extends JsonResource
{
    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'id'   => $this->resource['id'],
            'name' => $this->resource['name'],
        ];
    }
}
