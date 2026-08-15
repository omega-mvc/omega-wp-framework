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

namespace Omega\Application;

use Omega\Application\Exceptions\HeaderNotFoundException;

use function sprintf;
use function wp_get_theme;

/**
 * Theme application implementation for the Omega framework.
 *
 * This class represents a concrete application instance specifically designed
 * to bootstrap and manage a WordPress theme within the Omega framework.
 *
 * It extends the AbstractApplication class and inherits all core framework
 * responsibilities, including service container initialization, service provider
 * registration, routing setup, and dependency management.
 *
 * In addition to the base application behavior, this class enforces the
 * structural constraints required for WordPress themes, ensuring that the
 * provided base path contains a valid theme directory with a proper entry file
 * (typically style.css).
 *
 * The ApplicationTheme class acts as the bridge between the Omega framework
 * and the WordPress theme lifecycle, providing a structured and predictable
 * initialization flow for theme-based applications.
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL V3.0+
 * @version   1.0.0
 */
class ApplicationTheme extends Application
{
    #region Constants
    /**
     * The name of the framework.
     *
     * This constant defines the official name of the core framework.
     * It is used as a stable identifier across the application lifecycle
     * and should not be changed at runtime.
     *
     * @var string
     */
    protected const string NAME = 'Omega Theme';

    /**
     * The version of the framework.
     *
     * This constant defines the current version of the core framework.
     * It is used for version tracking, compatibility checks, and internal
     * framework identification.
     *
     * It should be updated only when releasing a new framework version.
     *
     * @var string
     */
    protected const string VERSION = '1.0.0';
    #endregion

    #region Lifecycle
    /**
     * Create and initialize a new theme application instance.
     *
     * This constructor delegates the full initialization process to the
     * AbstractApplication layer and then ensures that the provided base path
     * conforms to the expected WordPress theme structure.
     *
     * A valid theme must exist at the given base path and include a style.css
     * file, which serves as the required theme entry point according to the
     * WordPress theme specification.
     *
     * If the expected theme entry file is missing, the initialization process
     * is interrupted and a FileNotFoundException is thrown, preventing the
     * application from entering an invalid state.
     *
     * @param string $id Unique identifier of the theme application.
     *                   This typically corresponds to the theme directory name.
     * @param string $basePath Absolute path to the root directory of the theme.
     *                         Must point to a valid WordPress theme folder.
     * @return void
     */
    public function __construct(string $id, string $basePath)
    {
        parent::__construct($id, $basePath);
    }
    #endregion

    #region WordPress Metadata
    /**
     * {@inheritdoc}
     *
     * @throws HeaderNotFoundException if the theme header is not found.
     */
    public function getHeaderField(string $headerKey): string
    {
        $theme = wp_get_theme($this->id);

        $value = (string) $theme->get($headerKey);

        if ($value === '') {
            throw new HeaderNotFoundException('Theme header "%s" not found.', $headerKey);
        }

        return $value;
    }
    #endregion
}
