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

namespace Omega\Database\ORM\Relations;

/**
 * HasMany
 *
 * Represents a one-to-many relationship where a single parent model
 * is associated with multiple related models.
 *
 * The foreign key is stored on the related models and references
 * the parent model's local key.
 *
 * This class inherits all behavior from AbstractHasOneOrMany
 * and differs only in the expected cardinality of results.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Eloquent\Relations
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class HasMany extends AbstractHasOneOrMany
{
}
