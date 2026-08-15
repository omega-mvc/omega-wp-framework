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

use Omega\Application\Exceptions\FileNotFoundException;
use Omega\Application\Exceptions\HeaderNotFoundException;
use Omega\Application\Exceptions\WordPressEnvironmentException;

use function defined;
use function file_exists;
use function function_exists;
use function get_file_data;
use function sprintf;

/**
 * Concrete implementation of the Omega application.
 *
 * This class represents the final, framework-level application instance
 * and is responsible for defining the core identity of Omega, including
 * its official name and version.
 *
 * It extends the AbstractApplication, inheriting all container
 * initialization, service provider registration, routing, and migration
 * orchestration logic.
 *
 * The concrete layer does not participate in infrastructure setup.
 * Its role is strictly to provide stable framework metadata and act
 * as the top-level entry point of the application runtime.
 *
 * @category  Omega
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class ApplicationPlugin extends Application
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
    protected const string NAME = 'Omega Plugin';

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
     * Creates a new concrete application instance.
     *
     * This constructor initializes the application by delegating the full
     * bootstrap process to the AbstractApplication layer.
     *
     * It binds the application identity (ID), configures the base paths,
     * and triggers the registration of core service providers, user-defined
     * providers, and internal container aliases.
     *
     * This class does not introduce additional initialization logic because
     * its responsibility is limited to defining framework-level metadata
     * such as name and version.
     *
     * @param string $id Unique identifier of the application instance.
     *                   Must be a non-empty string.
     * @param string $basePath Absolute path to the root directory of the application.
     *                         Must be a non-empty string pointing to a valid location.
     * @return void
     * @throws FileNotFoundException Thrown when the plugin entry file is missing in the provided base path,
     */
    public function __construct(string $id, string $basePath)
    {
        parent::__construct($id, $basePath);

        if (!file_exists($basePath . "/$id.php")) {
            throw new FileNotFoundException(
                "The plugin file for %s does not exist in the specified plugin root, "
                . "in ApplicationFactory::createPlugin configure application_root.",
                $id
            );
        }
    }
    #endregion

    #region WordPress Metadata
    /**
     * {@inheritdoc}
     *
     * @throws HeaderNotFoundException if the plugin header is not found.
     * @throws WordPressEnvironmentException if the WordPress environment is not available.
     */
    public function getHeaderField(string $headerKey): string
    {
        $pluginFile =  "{$this->getAppRoot()}/{$this->getId()}.php";

        if (!function_exists('get_file_data')) {
            if (defined('ABSPATH')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            } else {
                throw new WordPressEnvironmentException(
                    'WordPress environment is not available.'
                );
            }
        }

        $data = get_file_data(
            $pluginFile,
            [$headerKey => $headerKey]
        );

        $value = $data[$headerKey] ?? '';

        if ($value === '') {
            throw new HeaderNotFoundException('Plugin header "%s" not found.', $headerKey);
        }

        return $value;
    }
    #endregion
}
