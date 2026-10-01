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
use Omega\Database\ORM\QueryBuilder;
use Omega\Database\Schema\Schema;
use ReflectionException;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function array_walk;
use function basename;
use function current_time;
use function error_log;
use function file_exists;
use function get_option;
use function glob;
use function in_array;
use function is_scalar;
use function is_string;
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
    /** @var string|array<int|string, string> Application identifier used as table prefix. */
    protected string|array $prefix;

    /** @var string Base filesystem path where migrations are located. */
    protected string $path;

    /** @var ApplicationInterface Application instance used for configuration and versioning. */
    protected ApplicationInterface $app;

    /** @var string Name of the migrations tracking database table. */
    protected string $tableName;

    /** @var string Previous installed application version used for conditional migrations. */
    protected string $oldVersion;
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
        $oldVersion       = get_option("{$this->prefix}_version", $app->getHeaderField('Version'));
        $this->oldVersion = is_scalar($oldVersion) ? (string) $oldVersion : '';
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
                $table->id('id');
                $table->string('name');
                $table->string('file');
                $table->timestamps();
            });
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
        $migration = require $file;

        if (! $migration instanceof AbstractMigration) {
            return false;
        }

        $migration->setOldVersion($this->oldVersion);

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
        $files = $this->migrationFiles();

        if (!$files) {
            return null;
        }

        $this->maybeCreateMigrationsTable();

        return $this->applyMigrations($files);
    }

    /**
     * Collect the migration files shipped with the application and with the extra folders.
     *
     * @return array<int, string> Absolute paths of the available migration files.
     */
    private function migrationFiles(): array
    {
        $folders = array_map(
            fn (string $folder): array => $this->migrationFolder($folder),
            $this->app->getMigrationFolders()
        );

        return array_merge($this->migrationFolder("$this->path/database/migrations"), ...$folders);
    }

    /**
     * List the migration files contained in a single directory.
     *
     * @param  string  $directory  Absolute path of the directory to scan.
     * @return array<int, string> Absolute paths of the migration files found, empty when none.
     */
    private function migrationFolder(string $directory): array
    {
        $files = glob("$directory/*.php");

        return $files === false ? [] : $files;
    }

    /**
     * Execute the pending migrations and record them in the migrations table.
     *
     * @param  array<int, string>  $files  Absolute paths of the available migration files.
     * @return array<int, string> The identifiers of the applied migrations.
     * @throws ReflectionException
     */
    private function applyMigrations(array $files): array
    {
        $model      = Database::table($this->tableName);
        $migrations = $model->select('name')->get()->pluck('name')->toArray();

        $executed = array_map(
            fn (string $file): ?string => $this->applyMigration($file),
            $this->pendingMigrations($files, $migrations)
        );

        return array_values(
            array_filter(
                $executed,
                static fn (?string $migrationId): bool => $migrationId !== null
            )
        );
    }

    /**
     * Filter out the migrations already recorded in the migrations table.
     *
     * @param  array<int, string>     $files       Absolute paths of the available migration files.
     * @param  array<array-key, mixed> $migrations Identifiers of the already applied migrations.
     * @return array<int, string> Absolute paths of the migrations still to apply.
     */
    private function pendingMigrations(array $files, array $migrations): array
    {
        return array_values(
            array_filter(
                $files,
                static fn (string $file): bool => !in_array(basename($file, '.php'), $migrations, true)
            )
        );
    }

    /**
     * Execute a single pending migration and record it.
     *
     * @param  string  $file  Absolute path of the migration file.
     * @return string|null The identifier of the applied migration, or null when it failed.
     * @throws ReflectionException
     */
    private function applyMigration(string $file): ?string
    {
        if (!$this->processMigrationFile($file)) {
            return null;
        }

        $migrationId = basename($file, '.php');

        $this->recordMigration($file, $migrationId);

        return $migrationId;
    }

    /**
     * Store the execution of a migration in the migrations table.
     *
     * @param  string  $file          Absolute path of the migration file.
     * @param  string  $migrationId   Identifier of the migration.
     * @return void
     * @throws ReflectionException
     */
    private function recordMigration(string $file, string $migrationId): void
    {
        Database::insert(Database::getTableName($this->tableName), [
            'name'       => $migrationId,
            'file'       => $file,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
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

        $this->rollbackMigrations($model, $model->get()->toArray());

        return $this->run();
    }

    /**
     * Roll back every recorded migration still pointing at an existing file.
     *
     * @param  QueryBuilder  $model       The query builder bound to the migrations table.
     * @param  array<array-key, mixed>  $migrations  The recorded migration rows.
     * @return void
     * @throws ReflectionException
     */
    private function rollbackMigrations(QueryBuilder $model, array $migrations): void
    {
        $rows = $this->rollbackableRows($migrations);

        array_walk(
            $rows,
            function (array $row) use ($model): void {
                $this->rollbackMigration($model, $row);
            }
        );
    }

    /**
     * Keep only the recorded rows carrying an existing migration file.
     *
     * @param  array<array-key, mixed>  $migrations  The recorded migration rows.
     * @return array<int, array{row: array<array-key, mixed>, file: string}> The rollback targets.
     */
    private function rollbackableRows(array $migrations): array
    {
        $rows = array_filter(
            $migrations,
            static fn (mixed $row): bool => is_array($row)
        );

        return array_values(
            array_filter(
                array_map(
                    fn (array $row): ?array => $this->rollbackTarget($row),
                    $rows
                ),
                static fn (?array $target): bool => $target !== null
            )
        );
    }

    /**
     * Build the rollback target of a recorded row.
     *
     * @param  array<array-key, mixed>  $row  The recorded migration row.
     * @return array{row: array<array-key, mixed>, file: string}|null The rollback target,
     *                                                             or null when the row
     *                                                             carries no existing file.
     */
    private function rollbackTarget(array $row): ?array
    {
        $file = $row['file'] ?? null;

        if (!is_string($file)) {
            return null;
        }

        return file_exists($file) ? ['row' => $row, 'file' => $file] : null;
    }

    /**
     * Roll back a single recorded migration and drop its tracking row.
     *
     * @param  QueryBuilder  $model  The query builder bound to the migrations table.
     * @param  array{row: array<array-key, mixed>, file: string}  $target  The rollback target.
     * @return void
     * @throws ReflectionException
     */
    private function rollbackMigration(QueryBuilder $model, array $target): void
    {
        $migration = require_once $target['file'];

        if (!$migration instanceof AbstractMigration) {
            return;
        }

        $migration->down();

        $model->where(['id' => $target['row']['id']])->delete();
    }
    #endregion
}
