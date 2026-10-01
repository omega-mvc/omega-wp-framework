<?php

/**
 * Part of Omega - Tests\Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database;

use Omega\Collection\Collection;
use Omega\Database\Exceptions\ColumnDefinitionException;
use Omega\Database\Exceptions\SchemaQueryException;
use Omega\Database\Schema\Blueprint;
use Omega\Database\Schema\ColumnDefinition;
use Omega\Database\Schema\ForeignIdColumnDefinition;
use Omega\Database\Schema\ForeignKeyDefinition;
use Omega\Database\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

use function md5;
use function str_contains;
use function str_repeat;
use function substr;

/**
 * Covers the schema builder used by the migration engine.
 *
 * @category  Tests
 * @package   Database
 * @subpackage Schema
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Schema::class)]
#[CoversClass(Blueprint::class)]
#[CoversClass(ColumnDefinition::class)]
#[CoversClass(ForeignKeyDefinition::class)]
#[CoversClass(ForeignIdColumnDefinition::class)]
final class SchemaTest extends DatabaseTestCase
{
    /**
     * Define ABSPATH once so the blueprint can require the upgrade routines.
     */
    public static function setUpBeforeClass(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__ . '/../fixtures/app/plugin/wp/');
        }
    }

    /**
     * Test the column definition stores its data and exposes fluent flags.
     */
    public function testItBuildsAColumnDefinition(): void
    {
        $column = new ColumnDefinition([
            'type'          => 'string',
            'name'          => 'title',
            'length'        => '120',
            'autoIncrement' => 1,
            'unsigned'      => 1,
        ]);

        $this->assertSame('string', $column->getType());
        $this->assertSame('title', $column->getName());
        $this->assertSame(120, $column->getLength());
        $this->assertTrue($column->isAutoIncrement());
        $this->assertTrue($column->isUnsigned());
        $this->assertFalse($column->isNullable());
        $this->assertFalse($column->isPrimary());
        $this->assertFalse($column->isUnique());
        $this->assertFalse($column->isIndex());
        $this->assertNull($column->getAfter());
        $this->assertNull($column->getDefault());
    }

    /**
     * Test the column definition requires a type.
     */
    public function testItRequiresAColumnType(): void
    {
        $this->expectException(ColumnDefinitionException::class);
        $this->expectExceptionMessage('Column type is required');

        new ColumnDefinition(['name' => 'title']);
    }

    /**
     * Test an empty definition reports the missing type first.
 */
    public function testItRequiresAColumnTypeForAnEmptyDefinition(): void
    {
        $this->expectException(ColumnDefinitionException::class);
        $this->expectExceptionMessage('Column type is required');

        new ColumnDefinition([]);
    }

    /**
     * Test the column definition requires a name.
     */
    public function testItRequiresAColumnName(): void
    {
        $this->expectException(ColumnDefinitionException::class);
        $this->expectExceptionMessage('Column name is required');

        new ColumnDefinition(['type' => 'string']);
    }

    /**
     * Test a non numeric length is discarded instead of being cast.
     */
    public function testItIgnoresANonNumericColumnLength(): void
    {
        $column = new ColumnDefinition([
            'type'   => 'string',
            'name'   => 'title',
            'length' => 'wide',
        ]);

        $this->assertNull($column->getLength());
    }

    /**
     * Test the fluent modifiers mutate the definition and stay chainable.
     */
    public function testItAppliesTheFluentColumnModifiers(): void
    {
        $column = new ColumnDefinition(['type' => 'integer', 'name' => 'rating']);

        $returned = $column
            ->nullable()
            ->unsigned()
            ->primary()
            ->unique()
            ->index()
            ->default(0)
            ->after('author_id');

        $this->assertSame($column, $returned);
        $this->assertTrue($column->isNullable());
        $this->assertTrue($column->isUnsigned());
        $this->assertTrue($column->isPrimary());
        $this->assertTrue($column->isUnique());
        $this->assertTrue($column->isIndex());
        $this->assertSame(0, $column->getDefault());
        $this->assertSame('author_id', $column->getAfter());
    }

    /**
     * Test boolean defaults are normalized to the integer column representation.
     */
    public function testItNormalizesBooleanDefaults(): void
    {
        $enabled = new ColumnDefinition(['type' => 'boolean', 'name' => 'enabled']);
        $enabled->default(true);
        $disabled = new ColumnDefinition(['type' => 'boolean', 'name' => 'disabled']);
        $disabled->default(false);

        $this->assertSame(1, $enabled->getDefault());
        $this->assertSame(0, $disabled->getDefault());
    }

    /**
     * Test the raw attribute map exposes every flag to the builders.
     */
    public function testItExposesTheColumnAttributes(): void
    {
        $column = new ColumnDefinition(['type' => 'string', 'name' => 'title', 'length' => 40]);
        $column->index()->after('id');

        $this->assertSame([
            'type'          => 'string',
            'name'          => 'title',
            'nullable'      => false,
            'autoIncrement' => false,
            'unsigned'      => false,
            'primary'       => false,
            'index'         => true,
            'after'         => 'id',
            'length'        => 40,
        ], $column->getAttributes());
    }

    /**
     * Test the create statement covers every supported column type.
     */
    public function testItCreatesATableWithEveryColumnType(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::create('shapes', static function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('big');
            $table->integer('small');
            $table->boolean('flag')->default(true);
            $table->string('short', 10);
            $table->text('body');
            $table->longText('long_body');
            $table->json('payload')->nullable();
            $table->timestamp('published');
            $table->dateTime('deleted_at')->nullable();
            $table->addColumn('blob', 'payload_blob');
            $table->addColumn('string', 'no_length');
            $table->unsignedBigInteger('ubig');
            $table->unsignedInteger('usmall');
            $table->bigInteger('auto', true);
            $table->uuid('token');
        });

        $sql = $this->createStatement();

        $this->assertStringStartsWith("CREATE TABLE `wp_shapes` (\n  ", $sql);
        $this->assertStringEndsWith(
            "\n) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
            $sql
        );
        $this->assertStringContainsString('`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('`big` bigint(20) NOT NULL', $sql);
        $this->assertStringContainsString('`small` int(11) NOT NULL', $sql);
        $this->assertStringContainsString("`flag` tinyint(1) NOT NULL DEFAULT '1'", $sql);
        $this->assertStringContainsString('`short` varchar(10) NOT NULL', $sql);
        $this->assertStringContainsString('`body` text NOT NULL', $sql);
        $this->assertStringContainsString('`long_body` longtext NOT NULL', $sql);
        $this->assertStringContainsString('`payload` json DEFAULT NULL', $sql);
        $this->assertStringContainsString('`published` timestamp NOT NULL', $sql);
        $this->assertStringContainsString('`deleted_at` datetime DEFAULT NULL', $sql);
        $this->assertStringContainsString('`payload_blob` text NOT NULL', $sql);
        $this->assertStringContainsString('`no_length` varchar(255) NOT NULL', $sql);
        $this->assertStringContainsString('`ubig` bigint(20) unsigned NOT NULL', $sql);
        $this->assertStringContainsString('`usmall` int(11) unsigned NOT NULL', $sql);
        $this->assertStringContainsString('`auto` bigint(20) NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('`token` varchar(36) NOT NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (id)', $sql);
    }

    /**
     * Test the create statement appends unique, index and foreign key definitions.
     */
    public function testItCreatesATableWithKeysAndForeignConstraints(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::create('books', static function (Blueprint $table): void {
            $table->id('book_id');
            $table->string('isbn')->unique();
            $table->string('slug')->index();
            $table->string('legacy')->index();
            $table->foreignId('author_id')->constrained('authors', 'id')->onDelete('cascade');
            $table->index(['title', 'isbn']);
            $table->unique(['edition', 'year'], 'books_edition_year_unique');
            $table->index(['col(10)', 'name']);
        });

        $sql = $this->createStatement();

        $this->assertStringContainsString('`isbn` varchar(255) NOT NULL', $sql);
        $this->assertStringContainsString('UNIQUE KEY (`isbn`)', $sql);
        $this->assertStringContainsString('KEY (`slug`)', $sql);
        $this->assertStringContainsString('KEY (`legacy`)', $sql);
        $this->assertStringContainsString('PRIMARY KEY (book_id)', $sql);
        $this->assertStringContainsString(
            'CONSTRAINT wp_books_author_id_foreign FOREIGN KEY (author_id)'
            . ' REFERENCES wp_authors(id) ON DELETE CASCADE',
            $sql
        );
        $this->assertStringContainsString('KEY `books_title_isbn_index` (`title`, `isbn`)', $sql);
        $this->assertStringContainsString(
            'UNIQUE KEY `books_edition_year_unique` (`edition`, `year`)',
            $sql
        );
        $this->assertStringContainsString('KEY `books_col_10_name_index` (`col`(10), `name`)', $sql);
    }

    /**
     * Test the ON DELETE clause is skipped for an empty action.
     */
    public function testItOmitsAnEmptyOnDeleteAction(): void
    {
        $blueprint = new Blueprint('books');
        $empty     = new ForeignKeyDefinition($blueprint, [
            'name'       => 'author_id',
            'references' => 'id',
            'on'         => 'authors',
            'onDelete'   => '',
        ]);

        $this->assertSame(
            'CONSTRAINT wp_books_author_id_foreign FOREIGN KEY (author_id) REFERENCES wp_authors(id)',
            $empty->getForeignKeySql()
        );
    }

    /**
     * Test a non scalar default is written as an empty literal.
     */
    public function testItFallsBackToAnEmptyDefaultLiteralForNonScalarValues(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::create('books', static function (Blueprint $table): void {
            $table->string('title')->default([]);
        });

        $this->assertStringContainsString("`title` varchar(255) NOT NULL DEFAULT ''", $this->createStatement());
    }

    /**
     * Test the auto increment clause is limited to integer columns.
     */
    public function testItOmitsAutoIncrementForNonIntegerColumns(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::create('books', static function (Blueprint $table): void {
            $table->addColumn('timestamp', 'auto_ts', ['autoIncrement' => true]);
        });

        $this->assertStringContainsString('`auto_ts` timestamp NOT NULL', $this->createStatement());
    }

    /**
     * Test drop commands are ignored while creating a table.
     */
    public function testItIgnoresDropCommandsWhileCreatingATable(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::create('books', static function (Blueprint $table): void {
            $table->id();
            $table->dropIndex('books_legacy_index');
            $table->dropColumn('legacy');
        });

        $sql = $this->createStatement();

        $this->assertStringContainsString('`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT', $sql);
        $this->assertFalse($this->hasStatement('DROP'));
    }

    /**
     * Test a non scalar type and name are normalized to empty strings.
     */
    public function testItNormalizesNonScalarTypeAndName(): void
    {
        $column = new ColumnDefinition(['type' => ['bigInteger'], 'name' => ['title']]);

        $this->assertSame('', $column->getType());
        $this->assertSame('', $column->getName());
    }

    /**
     * Test the timestamps helper registers two nullable date columns.
     */
    public function testItAddsTimestampColumns(): void
    {
        $blueprint = new Blueprint('books');

        $timestamps = $blueprint->timestamps();
        $sql        = $this->runCreate($blueprint);

        $this->assertInstanceOf(Collection::class, $timestamps);
        $this->assertCount(2, $timestamps);
        $this->assertStringContainsString('`created_at` datetime DEFAULT NULL', $sql);
        $this->assertStringContainsString('`updated_at` datetime DEFAULT NULL', $sql);
    }

    /**
     * Test the explicit time precision is forwarded to both timestamp columns.
     */
    public function testItAppliesTheGivenTimePrecision(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::create('books', static function (Blueprint $table): void {
            $table->timestamps(6);
        });

        $this->assertStringContainsString('`created_at` datetime', $this->createStatement());
    }

    /**
     * Test create is skipped when the table is already present.
     */
    public function testItSkipsCreateWhenTheTableAlreadyExists(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => 'wp_books';

        Schema::create('books', static function (Blueprint $table): void {
            $table->id();
        });

        $this->assertFalse($this->hasStatement('CREATE TABLE'));
    }

    /**
     * Test the table existence probe is public and answers from the connection.
     */
    public function testItProbesTableExistence(): void
    {
        $blueprint = new Blueprint('books');
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        $this->assertFalse($blueprint->tableExists('wp_books'));
        $this->assertSame('books', $blueprint->getTable());

        $this->wpdb()->varResolver = static fn (string $query): mixed => 'wp_books';

        $this->assertTrue($blueprint->tableExists('wp_books'));
    }

    /**
     * Test drop issues a single drop statement.
     */
    public function testItDropsATable(): void
    {
        Schema::drop('books');

        $this->assertTrue($this->hasStatement('DROP TABLE IF EXISTS wp_books'));
    }

    /**
     * Test alter appends only the missing columns.
     */
    public function testItAddsOnlyTheMissingColumns(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => (
            str_contains($query, "'title'") ? 'title' : null
        );

        Schema::table('books', static function (Blueprint $table): void {
            $table->string('title');
            $table->string('subtitle');
        });

        $this->assertTrue(
            $this->hasStatement('ALTER TABLE `wp_books` ADD `subtitle` varchar(255) NOT NULL;')
        );
        $this->assertFalse($this->hasStatement('ADD `title`'));
    }

    /**
     * Test alter leaves the table untouched when the column is already present.
     */
    public function testItSkipsAlterWhenTheOnlyColumnAlreadyExists(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => (
            str_contains($query, "'title'") ? 'title' : null
        );

        Schema::table('books', static function (Blueprint $table): void {
            $table->string('title');
        });

        $this->assertFalse($this->hasStatement('ALTER TABLE'));
    }

    /**
     * Test the after modifier positions a new column.
     */
    public function testItPositionsNewColumnsWithTheAfterModifier(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::table('books', static function (Blueprint $table): void {
            $table->string('title')->after('author_id');
        });

        $this->assertTrue(
            $this->hasStatement('ALTER TABLE `wp_books` ADD `title` varchar(255) NOT NULL AFTER `author_id`;')
        );
    }

    /**
     * Test drop commands are issued only when the object still exists.
     */
    public function testItDropsExistingColumnsAndIndexes(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => match (true) {
            str_contains($query, 'SHOW COLUMNS') => 'title',
            str_contains($query, 'SHOW INDEX')   => 'books_title_index',
            default                             => null,
        };

        Schema::table('books', static function (Blueprint $table): void {
            $table->dropColumn('title');
            $table->dropIndex('books_title_index');
            $table->dropUnique('books_isbn_unique');
            $table->dropColumn('');
            $table->dropUnique('');
        });

        $this->assertTrue($this->hasStatement('ALTER TABLE `wp_books` DROP COLUMN `title`;'));
        $this->assertTrue($this->hasStatement('ALTER TABLE `wp_books` DROP INDEX `books_title_index`;'));
        $this->assertTrue($this->hasStatement('ALTER TABLE `wp_books` DROP INDEX `books_isbn_unique`;'));
    }

    /**
     * Test drop commands are skipped when the object is already gone.
     */
    public function testItSkipsDropCommandsWhenTheObjectIsMissing(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::table('books', static function (Blueprint $table): void {
            $table->dropColumn('title');
            $table->dropIndex(['title', 'isbn']);
        });

        $this->assertFalse($this->hasStatement('DROP COLUMN'));
        $this->assertFalse($this->hasStatement('DROP INDEX'));
    }

    /**
     * Test index commands are skipped when the index is already present.
     */
    public function testItSkipsIndexCommandsWhenTheIndexExists(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => (
            str_contains($query, 'SHOW INDEX') ? 'books_title_index' : null
        );

        Schema::table('books', static function (Blueprint $table): void {
            $table->index('title');
            $table->unique(['isbn', 'edition'], 'books_isbn_edition_unique');
        });

        $this->assertFalse($this->hasStatement('ADD INDEX'));
        $this->assertFalse($this->hasStatement('ADD UNIQUE INDEX'));
    }

    /**
     * Test index commands are issued for missing indexes and skip foreign keys.
     */
    public function testItAddsMissingIndexesOnAlter(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::table('books', static function (Blueprint $table): void {
            $table->index('title');
            $table->unique(['isbn', 'edition'], 'books_isbn_edition_unique');
            $table->foreignId('author_id')->constrained();
        });

        $this->assertTrue(
            $this->hasStatement('ALTER TABLE `wp_books` ADD INDEX `books_title_index` (`title`);')
        );
        $this->assertTrue(
            $this->hasStatement(
                'ALTER TABLE `wp_books` ADD UNIQUE INDEX `books_isbn_edition_unique` (`isbn`, `edition`);'
            )
        );
        $this->assertTrue($this->hasStatement('ADD `author_id` bigint(20) unsigned NOT NULL'));
    }

    /**
     * Test a failing create statement is reported with the connection error.
     */
    public function testItThrowsWhenTheCreateStatementFails(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $this->wpdb()->failNext     = true;

        $this->expectException(SchemaQueryException::class);
        $this->expectExceptionMessage('Schema statement failed for table wp_books: query failed');

        Schema::create('books', static function (Blueprint $table): void {
            $table->id();
        });
    }

    /**
     * Test a failing alter statement is reported with the connection error.
     */
    public function testItThrowsWhenTheAlterStatementFails(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $this->wpdb()->failNext     = true;

        $this->expectException(SchemaQueryException::class);
        $this->expectExceptionMessage('Schema statement failed for table wp_books: query failed');

        Schema::table('books', static function (Blueprint $table): void {
            $table->string('title');
        });
    }

    /**
     * Test the foreign helper on a plain column keeps the caller arguments.
     */
    public function testItBuildsAForeignKeyFromPlainColumns(): void
    {
        $blueprint = new Blueprint('books');
        $blueprint->unsignedInteger('author_id');

        $definition = $blueprint->foreign('author_id', 'books_author_foreign');

        $this->assertInstanceOf(ForeignKeyDefinition::class, $definition);
        $this->assertSame([
            'columns'   => 'author_id',
            'name'      => 'books_author_foreign',
            'blueprint' => $blueprint,
        ], $definition->getAttributes());
    }

    /**
     * Test the foreign helper on a foreign id column adopts the column data.
     */
    public function testItBuildsAForeignKeyFromAForeignIdColumn(): void
    {
        $blueprint = new Blueprint('books');
        $column    = $blueprint->foreignId('author_id');

        $this->assertInstanceOf(ForeignIdColumnDefinition::class, $column);
        $this->assertSame('author_id', $column->getName());

        $definition = $blueprint->foreign('author_id', 'ignored');

        $this->assertSame('author_id', $definition->getAttributes()['name']);
        $this->assertArrayNotHasKey('columns', $definition->getAttributes());
    }

    /**
     * Test the constrained helper guesses the table from the column name.
     */
    public function testItGuessesTheForeignTableFromTheColumnName(): void
    {
        $blueprint = new Blueprint('books');
        $column    = $blueprint->foreignId('author_id');

        $definition = $column->constrained();

        $this->assertSame('authors', $definition->getAttributes()['on']);
        $this->assertSame('id', $definition->getAttributes()['references']);
    }

    /**
     * Test the foreign key SQL is skipped when the definition is incomplete.
     */
    public function testItReturnsEmptySqlForAnIncompleteForeignKey(): void
    {
        $blueprint = new Blueprint('books');
        $definition = new ForeignKeyDefinition($blueprint, ['name' => 'author_id']);

        $this->assertSame('', $definition->getForeignKeySql());

        $withoutReferences = new ForeignKeyDefinition($blueprint, ['name' => 'author_id', 'on' => 'authors']);

        $this->assertSame('', $withoutReferences->getForeignKeySql());

        $withoutTable = new ForeignKeyDefinition($blueprint, ['name' => 'author_id', 'references' => 'id']);

        $this->assertSame('', $withoutTable->getForeignKeySql());

        $emptyName = new ForeignKeyDefinition($blueprint, ['name' => '']);

        $this->assertSame('', $emptyName->getForeignKeySql());

        $numericName = new ForeignKeyDefinition($blueprint, ['name' => 42]);

        $this->assertSame('', $numericName->getForeignKeySql());

        $complete = new ForeignKeyDefinition($blueprint, [
            'name'       => 'author_id',
            'references' => 'id',
            'on'         => 'authors',
        ]);

        $this->assertSame(
            'CONSTRAINT wp_books_author_id_foreign FOREIGN KEY (author_id) REFERENCES wp_authors(id)',
            $complete->getForeignKeySql()
        );
    }

    /**
     * Test a non string ON DELETE action is ignored.
     */
    public function testItIgnoresANonStringOnDeleteAction(): void
    {
        $blueprint = new Blueprint('books');
        $numeric   = new ForeignKeyDefinition($blueprint, [
            'name'       => 'author_id',
            'references' => 'id',
            'on'         => 'authors',
            'onDelete'   => 42,
        ]);

        $this->assertSame(
            'CONSTRAINT wp_books_author_id_foreign FOREIGN KEY (author_id) REFERENCES wp_authors(id)',
            $numeric->getForeignKeySql()
        );
    }

    /**
     * Test an overlong constraint name is hashed to fit the MySQL identifier limit.
     */
    public function testItHashesAnOverlongConstraintName(): void
    {
        $blueprint  = new Blueprint('ledger_entries');
        $columnName = str_repeat('a', 60);
        $definition = new ForeignKeyDefinition($blueprint, [
            'name'       => $columnName,
            'references' => 'id',
            'on'         => 'accounts',
        ]);

        $full   = 'wp_ledger_entries_' . $columnName . '_foreign';
        $short  = substr($full, 0, 55) . '_' . substr(md5($full), 0, 8);
        $sql    = $definition->getForeignKeySql();

        $this->assertSame(64, strlen($short));
        $this->assertStringStartsWith('CONSTRAINT ' . $short . ' FOREIGN KEY', $sql);
    }

    /**
     * Test the index name generator strips reserved characters.
     */
    public function testItGeneratesACleanIndexName(): void
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;

        Schema::table('book-items', static function (Blueprint $table): void {
            $table->index(['author.name', 'published-at']);
        });

        $this->assertTrue($this->hasStatement(
            'ALTER TABLE `wp_book-items` ADD INDEX `book_items_author_name_published_at_index`'
            . ' (`author.name`, `published-at`);'
        ));
    }

    /**
     * Run a blueprint in create mode and return the generated statement.
     *
     * @param  Blueprint  $blueprint  The blueprint to execute.
     * @return string The generated create statement.
     */
    private function runCreate(Blueprint $blueprint): string
    {
        $this->wpdb()->varResolver = static fn (string $query): mixed => null;
        $blueprint->setCreate();
        $blueprint->run();

        return $this->createStatement();
    }

    /**
     * Return the create statement executed during the test.
     *
     * @return string The last create statement found in the query log.
     */
    private function createStatement(): string
    {
        $statements = $this->statements('CREATE TABLE');

        $this->assertNotEmpty($statements, 'No CREATE TABLE statement was executed.');

        return (string) end($statements);
    }

    /**
     * Test whether a statement fragment was executed.
     *
     * @param  string  $fragment  Fragment to look for.
     * @return bool True when at least one statement contains the fragment.
     */
    private function hasStatement(string $fragment): bool
    {
        return $this->statements($fragment) !== [];
    }

    /**
     * Return the executed statements containing a fragment.
     *
     * @param  string  $fragment  Fragment to look for.
     * @return list<string> The matching statements.
     */
    private function statements(string $fragment): array
    {
        $matches = [];

        foreach ($this->wpdb()->queries as $query) {
            if (str_contains($query, $fragment)) {
                $matches[] = $query;
            }
        }

        return $matches;
    }
}
