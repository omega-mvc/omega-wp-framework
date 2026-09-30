<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database\Fixtures;

use Omega\Database\ORM\AbstractModel;
use Omega\Database\ORM\Casts\BooleanCast;

/**
 * Model declaring its casts as ready to use cast instances.
 *
 * @category  Tests
 * @package   Database
 * @subpackage Fixtures
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class InstancedCastItem extends AbstractModel
{
    /**
     * Database table name.
     */
    protected string $table = 'instanced_cast_items';

    /**
     * Casts declared as ready to use cast instances.
     *
     * @return array<string, mixed> The configured attribute casts.
     */
    protected function casts(): array
    {
        return [
            'state' => new BooleanCast(),
        ];
    }
}
