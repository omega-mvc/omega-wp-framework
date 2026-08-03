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
 * HasOne
 *
 * Represents a one-to-one relationship where a single parent model
 * is associated with exactly one related model.
 *
 * The foreign key is stored on the related model and references
 * the parent model's local key.
 *
 * Although structurally identical to HasMany, this relation
 * enforces a single-result semantic at the ORM level.
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
class HasOne extends AbstractHasOneOrMany
{
}
