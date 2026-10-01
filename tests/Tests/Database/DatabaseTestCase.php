<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database;

use Omega\Application\ApplicationFactory;
use Omega\Application\ApplicationPlugin;
use PHPUnit\Framework\TestCase;
use Tests\Routing\Support\WPDB;
use Tests\Routing\WordPressRuntime;

use function Omega\Application\slash;

/**
 * Base test case for the Database package.
 *
 * Resets the shared WordPress runtime registries and the wpdb mock
 * between tests, and boots a minimal plugin application so the model
 * factory can resolve the database service.
 *
 * @category  Tests
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class DatabaseTestCase extends TestCase
{
/**
     * Reset the WordPress runtime registries and the wpdb mock state.
     */
    protected function setUp(): void
    {
        parent::setUp();

        WordPressRuntime::reset();
        WordPressRuntime::$fileHeaders['Version'] = '1.0.0';
        $this->wpdb()->reset();

        ApplicationFactory::createPlugin(
            'sample',
            slash(path: __DIR__ . '/../fixtures/app/plugin/sample')
        );
    }

    /**
     * Return the global wpdb mock instance.
     *
     * @return WPDB The test double registered in the global scope.
     */
    protected function wpdb(): WPDB
    {
        /** @var WPDB $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        return $wpdb;
    }

    /**
     * Boot a fresh sample plugin application.
     *
     * @return ApplicationPlugin The booted plugin instance.
     */
    protected function pluginApp(): ApplicationPlugin
    {
        return ApplicationFactory::createPlugin(
            'sample',
            slash(path: __DIR__ . '/../fixtures/app/plugin/sample')
        );
    }
}
