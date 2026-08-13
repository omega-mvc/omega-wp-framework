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

use function add_filter;
use function str_replace;

/**
 * Service provider responsible for bootstrapping the database layer.
 *
 * Registers core database services into the application container,
 * including the database manager and migration system.
 *
 * This provider also applies runtime SQL compatibility adjustments
 * required for WordPress database handling, such as restoring
 * native SQL NULL operators after query preparation.
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

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        add_filter('query', [$this, 'restoreNullOperators']);
    }
    #endregion

    #region Query Filters
    /**
     * Restore native SQL NULL operators in prepared WordPress queries.
     *
     * WordPress database preparation utilities automatically quote placeholder
     * values, which prevents proper generation of SQL NULL conditions such as
     * `IS NULL` and `IS NOT NULL`.
     *
     * This method replaces internally generated placeholder tokens with their
     * corresponding native SQL operators after query preparation has completed.
     *
     * The replacement is intentionally lightweight because the method is executed
     * globally through the WordPress `query` filter for every database query.
     *
     * @param string $query The prepared SQL query string.
     * @return string The normalized SQL query with restored NULL operators.
     */
    public function restoreNullOperators(string $query): string
    {
        return str_replace(
            ["IS '!#####NULL#####!'", "IS NOT '!#####NULL#####!'"],
            ['IS NULL', 'IS NOT NULL'],
            $query
        );
    }
    #endregion
}
