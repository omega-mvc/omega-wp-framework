<?php

/**
 * Part of Omega - Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Database;

use Omega\Container\ServiceProvider;
use Omega\Database\Migrations\Migrator;

/**
 * Service provider responsible for bootstrapping the database layer.
 *
 * Registers core database services into the application container,
 * including the database manager and migration system.
 *
 * @category  Omega
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class DatabaseServiceProvider extends ServiceProvider
{
    #region Container
    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('database', function () use ($app) {
            return new Database($app);
        });

        $this->app->singleton('migrator', function () {
            return new Migrator($this->app);
        });
    }
    #endregion
}
