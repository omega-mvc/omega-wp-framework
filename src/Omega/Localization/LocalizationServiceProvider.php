<?php

/**
 * Part of Omega - Localization Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Localization;

use InvalidArgumentException;
use Omega\Container\ServiceProvider;
use ReflectionException;

use function add_action;
use function load_plugin_textdomain;
use function load_theme_textdomain;
use function ltrim;
use function sprintf;
use function str_replace;

/**
 * Registers the application text domain with WordPress.
 *
 * Localization is enabled through the application configuration:
 * - `app.translation.enable` (bool) toggles text domain loading.
 * - `app.translation.type` ('plugin' | 'theme') selects the WordPress API
 *   used to register the language files.
 *
 * Language files are resolved from the `resources/languages` directory of the
 * application root. Plugin applications receive a path relative to the
 * WordPress plugins directory (WordPress prefixes every plugin language path
 * with WP_PLUGIN_DIR), while theme applications receive the absolute path.
 *
 * @category  Omega
 * @package   Localization
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class LocalizationServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        add_action('init', [$this, 'init']);
    }

    /**
     * Register the application text domain with the WordPress runtime.
     *
     * @return void
     * @throws ReflectionException Thrown when the config service cannot be resolved.
     */
    public function init(): void
    {
        $config = $this->app->resolve('config');

        if (!$config->boolean('app.translation.enable')) {
            return;
        }

        $type = $config->string('app.translation.type');

        match ($type) {
            'theme' => load_theme_textdomain(
                $this->app->getId(),
                $this->app->getBasePath() . '/resources/languages'
            ),
            'plugin' => load_plugin_textdomain(
                $this->app->getId(),
                false,
                $this->pluginRelativePath() . '/resources/languages'
            ),
            default => throw new InvalidArgumentException(
                sprintf('Invalid translation type "%s" configured.', $type)
            ),
        };
    }

    /**
     * Resolve the application path relative to the WordPress plugins directory.
     *
     * WordPress prefixes plugin language paths with WP_PLUGIN_DIR, so an
     * absolute application path would be prepended twice and never resolve.
     *
     * @return string The application root relative to WP_PLUGIN_DIR.
     */
    private function pluginRelativePath(): string
    {
        return ltrim(
            str_replace(WP_PLUGIN_DIR, '', $this->app->getBasePath()),
            '/\\'
        );
    }
}
