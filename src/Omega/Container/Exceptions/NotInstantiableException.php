<?php

/**
 * Part of Omega - Container Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Container\Exceptions;

use function sprintf;

/**
 * Thrown when the container attempts to instantiate a non-instantiable type.
 *
 * This typically occurs when resolving an interface, abstract class,
 * trait, or any class that cannot be instantiated directly.
 *
 * @category   Omega
 * @package    Container
 * @subpackage Exceptions
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class NotInstantiableException extends AbstractContainerException
{
    /**
     * Create a new exception instance.
     *
     * @param string $className Non-instantiable class or interface.
     */
    public function __construct(string $className)
    {
        parent::__construct(
            sprintf('Class "%s" is not instantiable.', $className)
        );
    }
}
