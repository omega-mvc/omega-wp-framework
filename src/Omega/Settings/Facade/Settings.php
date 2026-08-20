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

namespace Omega\Settings\Facade;

use Omega\Facade\AbstractFacade;
use Omega\Settings\SettingsRepository;

/**
 * Facade providing a static interface to the SettingsRepository service.
 *
 * This facade acts as an application-level proxy to the underlying settings storage layer,
 * offering a simplified and globally accessible API for retrieving and mutating persistent
 * configuration values.
 *
 * It abstracts the underlying implementation details of the SettingsRepository, allowing
 * consumers to interact with application settings in a consistent and framework-agnostic way.
 *
 * Designed primarily for WordPress integration, it enables centralized access to plugin or
 * application settings without requiring direct dependency injection.
 *
 * @category   Omega
 * @package    Settings
 * @subpackage Facade
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 *
 * @method static bool has(string $key)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static string string(string $key, ?string $default = null)
 * @method static bool boolean(string $key, bool|string $default = false)
 * @method static int integer(string $key, ?int $default = null)
 * @method static array<int|string, mixed> getAll()
 * @method static bool update(string $key, mixed $value)
 * @method static bool delete(string $key)
 *
 * @see SettingsRepository
 */
class Settings extends AbstractFacade
{
    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor(): string
    {
        return 'settings';
    }
}
