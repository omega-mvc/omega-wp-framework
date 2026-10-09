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

namespace Omega\Database\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a bulk insert receives rows with mismatched columns.
 *
 * Bulk inserts generate a single column list from the first row and therefore
 * require every subsequent row to declare the exact same columns in the exact
 * same order. When a row diverges, continuing would silently write values into
 * the wrong columns, so the operation is rejected explicitly instead.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Exceptions
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class BulkInsertException extends InvalidArgumentException
{
}
