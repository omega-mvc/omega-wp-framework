<?php

/**
 * Part of Omega - Facade Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Facade;

use Omega\Facade\Exception\FacadeObjectNotSetException;

/**
 * Contract for defining a facade accessor.
 *
 * Implementations must return the container binding key that identifies
 * the underlying service instance.
 *
 * @category  Omega
 * @package   Facade
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
interface FacadeInterface
{
    #region Resolution
    /**
     * Get the container binding key for the facade.
     *
     * This method must be implemented by concrete facades to define
     * which service should be resolved from the container.
     *
     * @return string The container binding identifier.
     * @throws FacadeObjectNotSetException If not implemented by the concrete facade.
     */
    public static function getFacadeAccessor(): string;
    #endregion
}
