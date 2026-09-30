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

use Omega\Database\Migrations\AbstractMigration;
use Omega\Database\Migrations\Migrator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Database\Fixtures\MigrationApplication;
use Tests\Routing\WordPressRuntime;

use function file_get_contents;
use function file_put_contents;
use function ini_set;
use function str_contains;
use function str_repeat;
use function sys_get_temp_dir;

/**
 * Covers the migration runner and the migration contract.
 *
 * @category  Tests
 * @package   Database
 * @subpackage Migrations
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Migrator::class)]
#[CoversClass(AbstractMigration::class)]
final class MigrationsTest extends DatabaseTestCase
{
    /**
     * Fixtures live outside the plugin tree so the migrator only sees what the tests declare.
     */
    public static function setUpBeforeClass(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__ . '/../fixtures/app/plugin/wp/');
        }
    }

    /**
     * Redirect the migrator error log so it never pollutes the test output.
     */
    protected function setUp(): void
    {
        parent::setUp();

        file_put_contents($this->errorLogFile(), '');
        ini_set('error_log', $this->errorLogFile());
    }

    /**
     * Test the migrator reads the plugin version from the WordPress options.
     */
    public function testItReadsTheOldVersionFromTheOptions(): void
    {
        WordPressRuntime::$options['migrations_version'] = '2.5.0';

        $this->assertSame('2.5.0', $this->oldVersionOf($this->migrator()));
    }

    /**
     * Test a non scalar option is discarded instead of being cast.
     */
    public function testItDiscardsANonScalarOldVersion(): void
    {
        WordPressRuntime::$options['migrations_version'] = ['2.5.0'];

        $this->assertSame('', $this->oldVersionOf($this->migrator()));
    }

    /**
     * Test the migrations table is created only when it is missing.
     */
    public function testItCreatesTheMigrationsTable(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        $this->migrator()->maybeCreateMigrationsTable();

        $this->assertTrue($this->hasStatement('CREATE TABLE `wp_migrations_migrations`'));
        $this->assertTrue($this->hasStatement('`name` varchar(255) NOT NULL'));
        $this->assertTrue($this->hasStatement('`file` varchar(255) NOT NULL'));
        $this->assertTrue($this->hasStatement('`created_at` datetime DEFAULT NULL'));
        $this->assertTrue($this->hasStatement('`updated_at` datetime DEFAULT NULL'));
    }

    /**
     * Test the migrations table creation is skipped when it already exists.
     */
    public function testItSkipsTheMigrationsTableCreation(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => 'wp_migrations_migrations';

        $this->migrator()->maybeCreateMigrationsTable();

        $this->assertFalse($this->hasStatement('CREATE TABLE'));
    }

    /**
     * Test a failing table creation is logged instead of bubbling up.
     */
    public function testItLogsAFailingMigrationsTableCreation(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $this->wpdb()->failNext     = true;

        $log = $this->captureErrorLog(function (): void {
            $this->migrator()->maybeCreateMigrationsTable();
        });

        $this->assertStringContainsString(
            'Omega WP: could not create migrations table migrations_migrations: '
            . 'Schema statement failed for table wp_migrations_migrations: query failed',
            $log
        );
    }

    /**
     * Test a migration file returning a migration is executed.
     */
    public function testItProcessesAMigrationFile(): void
    {
        WordPressRuntime::$options['migrations_version'] = '3.1.4';

        $migrator = $this->migrator();
        $file     = $this->migrationFile('2026_01_01_000000_create_widgets_table');

        $this->assertTrue($migrator->processMigrationFile($file));
        $this->assertTrue(WordPressRuntime::$options['widgets_migration_up']);
        $this->assertSame('3.1.4', WordPressRuntime::$options['widgets_migration_version']);
    }

    /**
     * Test a migration file that is not a migration is skipped.
     */
    public function testItSkipsAFileThatIsNotAMigration(): void
    {
        $this->assertFalse(
            $this->migrator()->processMigrationFile($this->migrationFile('2026_01_03_000000_not_a_migration'))
        );
    }

    /**
     * Test a failing migration is logged and reported as not applied.
     */
    public function testItLogsAFailingMigration(): void
    {
        $migrator = $this->migrator();

        $log = $this->captureErrorLog(function () use ($migrator): void {
            $this->assertFalse(
                $migrator->processMigrationFile($this->migrationFile('2026_01_02_000000_broken_widgets_migration'))
            );
        });

        $this->assertTrue(WordPressRuntime::$options['broken_migration_up']);
        $this->assertStringContainsString(
            'Omega WP: migration 2026_01_02_000000_broken_widgets_migration failed: migration exploded',
            $log
        );
    }

    /**
     * Test the run returns null when the application ships no migration.
     */
    public function testItReturnsNullWithoutMigrationFiles(): void
    {
        $this->assertNull($this->migrator($this->setFixturePath('/fixtures/database/migrations-extra'))->run());
    }

    /**
     * Test an unreadable migration directory is handled as an empty list.
     */
    public function testItReturnsNullWhenTheMigrationPatternFails(): void
    {
        $migrator = $this->migrator(str_repeat('/omega', 1200));
        $handler  = static fn (): bool => true;

        set_error_handler($handler);

        try {
            $applied = $migrator->run();
        } finally {
            restore_error_handler();
        }

        $this->assertNull($applied);
    }

    /**
     * Test the run applies every pending migration and records it.
     */
    public function testItAppliesThePendingMigrations(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        $applied = $this->migrator()->run();

        $this->assertSame(['2026_01_01_000000_create_widgets_table'], $applied);
        $this->assertTrue(WordPressRuntime::$options['widgets_migration_up']);
        $this->assertTrue(WordPressRuntime::$options['broken_migration_up']);
        $this->assertFalse(isset(WordPressRuntime::$options['broken_migration_down']));

        $this->assertCount(1, $this->wpdb()->inserts);
        $this->assertSame('wp_migrations_migrations', $this->wpdb()->inserts[0][0]);
        $this->assertSame('2026_01_01_000000_create_widgets_table', $this->wpdb()->inserts[0][1]['name']);
        $this->assertSame(
            $this->migrationFile('2026_01_01_000000_create_widgets_table'),
            $this->wpdb()->inserts[0][1]['file']
        );
        $this->assertSame('2026-01-01 00:00:00', $this->wpdb()->inserts[0][1]['created_at']);
        $this->assertSame('2026-01-01 00:00:00', $this->wpdb()->inserts[0][1]['updated_at']);
    }

    /**
     * Test already applied migrations are not executed again.
     */
    public function testItSkipsAlreadyAppliedMigrations(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $this->wpdb()->results     = [
            (object) ['id' => 1, 'name' => '2026_01_01_000000_create_widgets_table'],
        ];

        $applied = $this->migrator()->run();

        $this->assertSame([], $applied);
        $this->assertFalse(isset(WordPressRuntime::$options['widgets_migration_up']));
        $this->assertSame([], $this->wpdb()->inserts);
    }

    /**
     * Test the extra migration folders are merged with the application ones.
     */
    public function testItMergesTheExtraMigrationFolders(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        $applied = $this->migrator(null, [
            $this->setFixturePath('/fixtures/database/migrations-extra'),
            $this->setFixturePath('/fixtures/database/migrations-empty'),
        ])->run();

        $this->assertSame([
            '2026_01_01_000000_create_widgets_table',
            '2026_02_01_000000_create_gadgets_table',
        ], $applied);
        $this->assertTrue(WordPressRuntime::$options['gadgets_migration_up']);
        $this->assertCount(2, $this->wpdb()->inserts);
    }

    /**
     * Test fresh rolls the recorded migrations back before running them again.
     */
    public function testItRollsBackTheRecordedMigrations(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $this->wpdb()->results     = [
            (object) [
                'id'   => 7,
                'name' => '2026_03_01_000000_create_things_table',
                'file' => $this->freshMigrationFile('2026_03_01_000000_create_things_table'),
            ],
        ];

        $applied = $this->freshMigrator()->fresh();

        $this->assertTrue(WordPressRuntime::$options['things_migration_down']);
        $this->assertCount(1, $this->wpdb()->deletes);
        $this->assertSame('wp_migrations_migrations', $this->wpdb()->deletes[0][0]);
        $this->assertSame(['id' => 7], $this->wpdb()->deletes[0][1]);
        // The wpdb double keeps returning the recorded row, so the ledger still
        // reports the migration as applied and run() has nothing left to do.
        $this->assertSame([], $applied);
    }

    /**
     * Test fresh ignores rows without a usable migration file.
     */
    public function testItIgnoresRowsWithoutAMigrationFile(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $this->wpdb()->results     = [
            (object) ['id' => 1, 'file' => 42],
            (object) ['id' => 2, 'file' => '/tmp/omega-missing-migration.php'],
        ];

        $applied = $this->freshMigrator()->fresh();

        $this->assertSame([], $this->wpdb()->deletes);
        $this->assertSame(['2026_03_01_000000_create_things_table'], $applied);
    }

    /**
     * Test fresh leaves the ledger untouched when the recorded file is not a migration.
     */
    public function testItIgnoresRecordedFilesThatAreNotMigrations(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $this->wpdb()->results     = [
            (object) [
                'id'   => 3,
                'name' => '2026_01_03_000000_not_a_migration',
                'file' => $this->setFixturePath(
                    '/fixtures/database/migrations-app/database/migrations/2026_01_03_000000_not_a_migration.php'
                ),
            ],
        ];

        $applied = $this->freshMigrator()->fresh();

        $this->assertSame([], $this->wpdb()->deletes);
        $this->assertSame(['2026_03_01_000000_create_things_table'], $applied);
    }

    /**
     * Test fresh without recorded migrations simply runs the pending ones.
     */
    public function testItRunsThePendingMigrationsOnAnEmptyLedger(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        $applied = $this->freshMigrator()->fresh();

        $this->assertSame(['2026_03_01_000000_create_things_table'], $applied);
        $this->assertSame([], $this->wpdb()->deletes);
    }

    /**
     * Build a migrator for the rollback fixture application.
     *
     * The rollback fixture lives in its own tree because fresh() loads the
     * recorded files with require_once(), so they must never be included by
     * another test of the same process.
     *
     * @return Migrator The migrator under test.
     */
    private function freshMigrator(): Migrator
    {
        return new Migrator(new MigrationApplication($this->setFixturePath('/fixtures/database/fresh-app')));
    }

    /**
     * Return the absolute path of a fixture migration file of the rollback application.
     *
     * @param  string  $name  File name without the extension.
     * @return string The absolute file path.
     */
    private function freshMigrationFile(string $name): string
    {
        return $this->setFixturePath("/fixtures/database/fresh-app/database/migrations/$name.php");
    }

    /**
     * Build the application used by the migrator tests.
     *
     * @param array<int, string> $migrationFolders Extra migration folders.
     * @return MigrationApplication The configured application.
     */
    private function migrationsApp(array $migrationFolders = []): MigrationApplication
    {
        return new MigrationApplication($this->setFixturePath('/fixtures/database/migrations-app'), $migrationFolders);
    }

    /**
     * Build a migrator for the fixture application.
     *
     * @param string|null             $basePath         Optional base path override.
     * @param array<int, string>      $migrationFolders Optional extra migration folders.
     * @return Migrator The migrator under test.
     */
    private function migrator(?string $basePath = null, array $migrationFolders = []): Migrator
    {
        if ($basePath === null) {
            return new Migrator($this->migrationsApp($migrationFolders));
        }

        return new Migrator(new MigrationApplication($basePath, $migrationFolders));
    }

    /**
     * Return the absolute path of a fixture migration file.
     *
     * @param  string  $name  File name without the extension.
     * @return string The absolute file path.
     */
    private function migrationFile(string $name): string
    {
        return $this->setFixturePath("/fixtures/database/migrations-app/database/migrations/$name.php");
    }

    /**
     * Run a callback while capturing the error log output.
     *
     * @param  callable(): void  $callback  Callback to run.
     * @return string The captured error log.
     */
    private function captureErrorLog(callable $callback): string
    {
        file_put_contents($this->errorLogFile(), '');

        $callback();

        return (string) file_get_contents($this->errorLogFile());
    }

    /**
     * Return the file the migrator error log is redirected to.
     *
     * @return string The error log path.
     */
    private function errorLogFile(): string
    {
        return sys_get_temp_dir() . '/omega-migrator-error.log';
    }

    /**
     * Test whether a statement fragment was executed.
     *
     * @param  string  $fragment  Fragment to look for.
     * @return bool True when at least one statement contains the fragment.
     */
    private function hasStatement(string $fragment): bool
    {
        foreach ($this->wpdb()->queries as $query) {
            if (str_contains($query, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the resolved old version from the constructor.
     *
     * @param  Migrator  $migrator  The migrator under test.
     * @return string The resolved old version.
     */
    private function oldVersionOf(Migrator $migrator): string
    {
        $property = new \ReflectionProperty($migrator, 'oldVersion');
        $value    = $property->getValue($migrator);

        return is_scalar($value) ? (string) $value : '';
    }
}
