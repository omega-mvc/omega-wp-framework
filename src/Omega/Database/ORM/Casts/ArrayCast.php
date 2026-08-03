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

use function json_decode;
use function wp_json_encode;

/**
 * ArrayCast
 *
 * Attribute caster responsible for converting JSON-encoded database values
 * into native PHP arrays and vice versa.
 *
 * This caster is typically used for columns that store structured data
 * such as lists, configurations, or nested objects encoded as JSON strings.
 *
 * During hydration, it decodes JSON into an associative array.
 * During persistence, it encodes arrays into a JSON string compatible
 * with the database storage layer.
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
class ArrayCast implements CastsAttributesInterface
{
	#region Casting
    /**
     * {@inheritdoc}
     */
    public function get(AbstractModel $model, string $key, mixed $value, array $attributes): mixed
    {
        return json_decode($value, true);
    }

    /**
     * {@inheritdoc}
     */
    public function set(AbstractModel $model, string $key, mixed $value, array $attributes): string|false
    {
        return wp_json_encode($value);
    }
	#endregion
}
