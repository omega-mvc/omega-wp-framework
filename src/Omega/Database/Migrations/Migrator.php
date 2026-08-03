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

use Omega\Application\ApplicationInterface;
use Omega\Database\Database;
use Omega\Database\Schema\Blueprint;
use Omega\Database\Schema\Schema;
use ReflectionException;
use Throwable;

use function array_merge;
use function basename;
use function current_time;
use function error_log;
use function file_exists;
use function get_option;
use function glob;
use function in_array;
use function method_exists;
use function sprintf;

/**
 * Migrator
 *
 * Responsible for discovering, executing, and tracking database migrations.
 *
 * This class scans migration directories, executes pending migration files,
 * stores execution state in a dedicated migrations table, and supports full
 * reset operations through the "fresh" method.
 *
 * It acts as the orchestration layer between the filesystem, database schema
 * builder, and application versioning system.
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
class Migrator
{
	#region Properties
    /** @var string|array Application identifier used as table prefix. */
    protected string|array $prefix;

    /** @var string Base filesystem path where migrations are located. */
    protected string $path;

    /** @var ApplicationInterface Application instance used for configuration and versioning. */
    protected ApplicationInterface $app;

    /** @var string Name of the migrations tracking database table. */
    protected string $tableName;

    /** @var mixed Previous installed application version used for conditional migrations. */
    protected mixed $oldVersion;
	#endregion

	#region Lifecycle
    /**
     * Create a new Migrator instance.
     *
     * Initializes application context, resolves migration paths, and loads
     * the previously installed application version from persistent storage.
     *
     * @param ApplicationInterface $app The application instance providing configuration and metadata.
     * @return void
     */
    public function __construct(ApplicationInterface $app)
    {
        $this->app        = $app;
        $this->prefix     = $app->getIdAsUnderscore();
        $this->path       = $app->getBasePath();
        $this->tableName  = "{$this->prefix}_migrations";
        $this->oldVersion = get_option("{$this->prefix}_version", $app->getHeaderField('Version'));
    }
	#endregion

	#region Schema
    /**
     * Ensure that the migrations tracking table exists.
     *
     * Creates the migrations table if it does not already exist,
     * using the schema builder to define structure and metadata.
     *
     * @return void
     */
    public function maybeCreateMigrationsTable(): void
    {
	    if (Database::tableExists($this->tableName)) {
		    return;
	    }

	    try {
		    Schema::create($this->tableName, function (Blueprint $table) {
			    /** @noinspection PhpRedundantOptionalArgumentInspection */
			    $table->id('id');
			    $table->string('name');
			    $table->string('file');
			    $table->timestamps();
		    } );
	    } catch (Throwable $e) {
		    // run() is called from a boot hook, so letting this escape would take the whole site
		    // down on every request. Log it and carry on: without this table run() simply finds
		    // no applied migrations, which is safe because each migration is itself idempotent.
		    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		    error_log(
				sprintf(
					'Omega WP: could not create migrations table %s: %s',
					$this->tableName,
					$e->getMessage()
				)
		    );
	    }
    }
	#endregion

	#region Migration Execution
    /**
     * Execute a single migration file.
     *
     * Loads the migration class from the given file, injects the previous
     * application version, and executes the "up" method.
     *
     * @param string $file Absolute path to the migration file.
     * @return bool True if the migration was executed successfully, false otherwise.
     */
    public function processMigrationFile(string $file): bool
    {
	    /** @var AbstractMigration $migration */
	    $migration = require $file;

	    if ( ! method_exists( $migration, 'up' ) ) {
		    return false;
	    }

	    $migration->setOldVersion( $this->oldVersion );

	    try {
		    $migration->up();
	    } catch (Throwable $e) {
		    // Never report a migration as applied when its schema change failed. run() skips
		    // anything already recorded, so recording a failure makes it permanent: the table
		    // or column stays missing and is never retried, and the only symptom is writing
		    // silently doing nothing.
		    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		    error_log(sprintf('Omega WP: migration %s failed: %s', basename($file, '.php'), $e->getMessage()));
		    return false;
	    }

	    return true;
    }

	/**
	 * Run all pending migrations.
	 *
	 * Scans configured migration directories, filters already executed migrations,
	 * executes pending ones, and records execution in the migrations table.
	 *
	 * @return array<int, string>|null List of applied migration identifiers or null if none found.
	 * @throws ReflectionException
	 */
    public function run(): ?array
    {
        $files = glob("$this->path/database/migrations/*.php");

        if (!empty($this->app->getMigrationFolders()) && is_array($this->app->getMigrationFolders())) {
            foreach ($this->app->getMigrationFolders() as $folder) {
                $extraFiles = glob("$folder/*.php");
                if ($extraFiles) {
                    $files = array_merge($files, $extraFiles);
                }
            }
        }

        if (!$files) {
			return null;
        }

        $this->maybeCreateMigrationsTable();

        $model = Database::table($this->tableName);

        $migrations = $model->select('name')->get()->pluck('name')->toArray();
        $applied = [];

        foreach ($files as $file) {
            $migrationId = basename($file, '.php');

            if (in_array($migrationId, $migrations, true)) {
                continue;
            }

            $migrated = $this->processMigrationFile($file);

            if ($migrated) {
                $migrations[] = $migrationId;
                $applied[]    = $migrationId;

                Database::insert(Database::getTableName($this->tableName), [
                    'name'       => $migrationId,
                    'file'       => $file,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            }
        }

        return $applied;
    }
	#endregion

	#region Rollback
	/**
	 * Rollback all applied migrations and re-run them.
	 *
	 * Executes the "down" method for all recorded migrations, removes their
	 * tracking entries, and then re-applies all migrations from scratch.
	 *
	 * @return array<int, string>|null List of re-applied migration identifiers or null.
	 * @throws ReflectionException
	 */
    public function fresh(): ?array
    {
        $model = Database::table($this->tableName);

        $migrations = $model->get();

        if (!$migrations->isEmpty()) {
            foreach ($migrations as $mg) {
                if (file_exists($mg->file)) {
                    $migration = require_once $mg->file;

                    if (method_exists($migration, 'down')) {
                        $migration->down();
	                    /** @noinspection PhpArrayWriteIsNotUsedInspection */
	                    $success[] = $mg->name;
                        $model->where(['id' => $mg->id])->delete();
                    }
                }
            }
        }

        return $this->run();
    }
	#endregion
}
