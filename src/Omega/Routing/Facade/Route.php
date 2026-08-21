<?php

/**
 * Part of Omega - Routing Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Routing\Facade;

use Omega\Facade\AbstractFacade;
use Omega\Routing\Router;

/**
 * @category   Omega
 * @package    Route
 * @subpackage Facade
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 *
 * @method static Router get(string $uri, array<int, string>|string|callable|null $action = null)
 * @method static Router post(string $uri, array<int, string>|string|callable|null $action = null)
 * @method static Router put(string $uri, array<int, string>|string|callable|null $action = null)
 * @method static Router delete(string $uri, array<int, string>|string|callable|null $action = null)
 * @method static Router patch(string $uri, array<int, string>|string|callable|null $action = null)
 * @method static Router prefix(string $prefix)
 * @method static Router guards(array<int, string|callable|array<int, string>> $guards)
 * @method static Router page(string $id, $options = [])
 *
 * @see Router
 */
class Route extends AbstractFacade
{
    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor(): string
    {
        return 'router';
    }
}
