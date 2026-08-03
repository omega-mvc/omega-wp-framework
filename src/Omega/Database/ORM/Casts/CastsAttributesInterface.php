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
 * CastsAttributesInterface
 *
 * Defines a contract for attribute casting within model hydration and persistence.
 *
 * Implementations of this interface are responsible for transforming raw database
 * values into meaningful PHP representations when reading data (get),
 * and converting PHP values back into database-compatible formats when writing data (set).
 *
 * This mechanism enables automatic type handling for fields such as dates,
 * JSON structures, enums, booleans, and custom value objects.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Eloquent
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
interface CastsAttributesInterface
{
	#region Casting
    /**
     * Transform the attribute value when retrieving it from the model.
     *
     * This method is invoked during model hydration (e.g., when data is fetched
     * from the database) to convert raw stored values into more expressive PHP types.
     *
     * @param AbstractModel $model The model instance the attribute belongs to.
     * @param string $key The attribute name being cast.
     * @param mixed $value The raw value retrieved from the database.
     * @param array<string, mixed> $attributes All raw model attributes.
     * @return mixed The transformed value to be used in the model instance.
     */
    public function get(AbstractModel $model, string $key, mixed $value, array $attributes): mixed;

    /**
     * Transform the attribute value before saving it to the database.
     *
     * This method is invoked during model persistence (e.g., insert or update)
     * to convert PHP-native values into a format compatible with the database layer.
     *
     * @param AbstractModel $model The model instance the attribute belongs to.
     * @param string $key The attribute name being cast.
     * @param mixed|null $value The PHP value assigned to the attribute.
     * @param array<string, mixed> $attributes All current model attributes.
     * @return mixed The transformed value suitable for database storage.
     */
    public function set(AbstractModel $model, string $key, mixed $value, array $attributes): mixed;
	#endregion
}
