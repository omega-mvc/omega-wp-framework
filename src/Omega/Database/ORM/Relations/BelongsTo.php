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
 * BelongsTo
 *
 * Represents an inverse relationship where the current model
 * holds the foreign key pointing to another model.
 *
 * In a BelongsTo relationship, the foreign key resides on the
 * current (child) model and references the primary key of the
 * related (parent) model.
 *
 * This is typically used to express ownership or dependency,
 * such as:
 * - A Post belongs to a User
 * - A Comment belongs to a Post
 *
 * The actual resolution logic is handled by AbstractRelation
 * and higher-level query builders.
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
class BelongsTo extends AbstractRelation
{
}
