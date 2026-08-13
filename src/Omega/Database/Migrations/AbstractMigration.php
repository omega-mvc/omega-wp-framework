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

namespace Omega\Database\Migrations;

/**
 * AbstractMigration
 *
 * Base implementation of a database migration.
 *
 * Provides shared functionality for all migrations, including version
 * tracking support via the "oldVersion" property. Concrete migrations
 * must implement the "up" and "down" methods.
 *
 * @category   Omega
 * @package    Database
 * @subpackage Migration
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
abstract class AbstractMigration implements MigrationInterface
{
    #region Properties
    /**
     * Previous application or database version at the time of execution.
     *
     * This value can be used to conditionally execute schema changes based
     * on version differences between releases.
     *
     * @var string
     */
    protected string $oldVersion;
    #endregion

    #region Lifecycle
    /**
     * Set the previous version of the application.
     *
     * This value is injected by the Migrator before executing the migration,
     * allowing migrations to adapt based on upgrade paths.
     *
     * @param string $version The previously installed application version.
     * @return void
     */
    public function setOldVersion(string $version): void
    {
        $this->oldVersion = $version;
    }
    #endregion

    #region Migration
    /**
     *{@inheritdoc}
     */
    abstract public function up(): void;

    /**
     *{@inheritdoc}
     */
    abstract public function down(): void;
    #endregion
}
