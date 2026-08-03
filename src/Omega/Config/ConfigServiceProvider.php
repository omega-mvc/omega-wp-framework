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

namespace Omega\Config;

use Omega\Container\ServiceProvider;

use function basename;
use function glob;
use function is_dir;

/**
 * ConfigServiceProvider registers configuration-related services into the application container.
 *
 * It is responsible for binding both the immutable configuration repository and the mutable
 * settings repository as singleton services within the application.
 *
 * The "config" service loads PHP configuration files from the application's /config directory,
 * aggregates them into a single array, and exposes them through a ConfigRepository instance.
 *
 * The "settings" service provides a persistent settings layer backed by the WordPress options API,
 * allowing runtime modification and storage of user or plugin-specific settings.
 *
 * This service provider acts as the central entry point for configuration management,
 * ensuring a consistent and shared state across the application lifecycle.
 *
 * @category  Omega
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class ConfigServiceProvider extends ServiceProvider
{
	#region Container
    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->app->singleton('config', function () {
            $configPath = $this->app->getBasePath() . '/config';
			$config     = [];
            if (is_dir($configPath)) {
                foreach (glob($configPath . '/*.php') as $file) {
                    $key          = basename($file, '.php');
                    $config[$key] = require $file;
                }
            }

            return new ConfigRepository($config);
        });
    }
	#endregion
}
