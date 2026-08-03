<?php

/**
 * Part of Omega - Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Database\ORM\Casts;

use Omega\Database\ORM\AbstractModel;

/**
 * BooleanCast
 *
 * Attribute caster responsible for normalizing boolean-like values
 * between the database layer and PHP runtime.
 *
 * It ensures consistent type handling by converting database values
 * (often stored as integers or strings) into proper boolean values
 * when reading, and converting them back into integer representation
 * when writing.
 *
 * This guarantees compatibility with storage systems that do not
 * natively support boolean types.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Eloquent\Casts
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class BooleanCast implements CastsAttributesInterface
{
	#region Casting
    /**
     * {@inheritdoc}
     */
    public function get(AbstractModel $model, string $key, mixed $value, array $attributes): bool
    {
        return (bool)$value;
    }

    /**
     * {@inheritdoc}
     */
    public function set(AbstractModel $model, string $key, mixed $value, array $attributes): int
    {
        return (int)$value;
    }
	#endregion
}
