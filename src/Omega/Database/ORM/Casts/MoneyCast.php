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

use function round;

/**
 * MoneyCast
 *
 * Attribute caster responsible for handling monetary values using
 * an integer-based storage strategy (e.g. cents) while exposing
 * a float representation at runtime.
 *
 * This approach avoids floating-point precision issues by storing
 * values as integers in the database and converting them to
 * human-readable decimal values when accessed in the application.
 *
 * During hydration, values are divided by 100 to represent currency units.
 * During persistence, values are multiplied and rounded to store
 * a safe integer representation.
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
class MoneyCast implements CastsAttributesInterface
{
    #region Casting
    /**
     * {@inheritdoc}
     */
    public function get(AbstractModel $model, string $key, mixed $value, array $attributes): int|float
    {
        return $value / 100;
    }

    /**
     * {@inheritdoc}
     */
    public function set(AbstractModel $model, string $key, mixed $value, array $attributes): int
    {
        return (int)round($value * 100);
    }
    #endregion
}
