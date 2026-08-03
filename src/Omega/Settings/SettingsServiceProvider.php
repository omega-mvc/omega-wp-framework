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

namespace Omega\Settings;

use Omega\Container\ServiceProvider;

/**
 * Service provider responsible for registering the Settings subsystem
 * within the application container.
 *
 * This provider binds the `settings` service as a singleton instance,
 * ensuring a single shared configuration state throughout the application
 * lifecycle.
 *
 * The Settings subsystem is designed to manage persistent, runtime-modifiable
 * application options stored in the WordPress options table. It provides
 * a structured API for reading, updating, and deleting user-defined or
 * system-level settings, while maintaining a merged state of default and
 * persisted values.
 *
 * By centralizing the registration of the SettingsRepository, this provider
 * ensures that all components of the application resolve a consistent and
 * synchronized settings instance from the container.
 *
 * This service is typically used in environments where application state
 * must persist across requests, such as WordPress plugin or theme contexts.
 *
 * @category   Omega
 * @package    Settings
 * @subpackage Facade
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
class SettingsServiceProvider extends ServiceProvider
{
	#region Container
    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->app->singleton('settings', function ($app) {
            return new SettingsRepository($app);
        });
    }
	#endregion
}
