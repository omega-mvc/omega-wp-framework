<?php

/**
 * Part of Omega - Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Application\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a required parameter is missing during application creation.
 *
 * This exception is used by the ApplicationFactory when essential configuration
 * values, such as the application ID or base path, are not provided or are empty.
 * It indicates an invalid or incomplete initialization state.
 *
 * @category   Omega
 * @package    Application
 * @subpackage Exception
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
final class MissingParameterException extends InvalidArgumentException
{
}
