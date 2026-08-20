<?php

/**
 * Part of Omega - Config Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Config\Facades;

use Omega\Config\ConfigRepository;
use Omega\Facade\AbstractFacade;

/**
 * Facade providing a static interface to the ConfigRepository service.
 *
 * This facade exposes the application's configuration layer through a global, static API,
 * allowing configuration values to be accessed in a simple and expressive way without
 * requiring direct dependency injection.
 *
 * It acts as a read-focused abstraction over the underlying configuration repository,
 * which aggregates configuration files into a unified in-memory structure.
 *
 * The facade is designed for application-wide configuration access, typically used during
 * bootstrapping, service resolution, and runtime feature toggling within the Omega framework.
 *
 * @category   Omega
 * @package    Config
 * @subpackage Facades
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static string string(string $key, ?string $default = null)
 * @method static int integer(string $key, ?int $default = null)
 * @method static bool boolean(string $key, ?bool $default = null)
 * @method static array<int|string, mixed> getAll()
 *
 * @see ConfigRepository
 */
class Config extends AbstractFacade
{
    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor(): string
    {
        return 'config';
    }
}
