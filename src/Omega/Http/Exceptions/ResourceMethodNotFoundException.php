<?php

/**
 * Part of Omega - Http Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Http\Exceptions;

use BadMethodCallException;

use function sprintf;

/**
 * Exception thrown when a method is called on a JsonResource that does not
 * exist on either the resource wrapper or the underlying resource instance.
 *
 * This exception is triggered by the dynamic proxy mechanism implemented
 * in JsonResource, which forwards method calls to the wrapped resource when
 * available. If the method cannot be resolved, this exception is raised to
 * clearly indicate an invalid or unsupported method invocation.
 *
 * It provides a more specific and domain-oriented alternative to the generic
 * BadMethodCallException within the HTTP Resource layer of the framework.
 *
 * @category   Omega
 * @package    Http
 * @subpackage Exceptions
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class ResourceMethodNotFoundException extends BadMethodCallException
{
    /**
     * Create a new exception instance.
     *
     * Accepts either a ready-to-use message or a printf-style format string
     * plus parameters for deferred interpolation.
     *
     * @param string $message   Error message or format string.
     * @param mixed  ...$parameters Optional substitution values for sprintf.
     */
    public function __construct(string $message, mixed ...$parameters)
    {
        if ($parameters !== []) {
            $message = sprintf($message, ...$parameters);
        }

        parent::__construct($message);
    }
}
