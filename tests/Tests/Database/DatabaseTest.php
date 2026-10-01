<?php

/**
 * Part of Omega - Tests\Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database;

use Omega\Application\Exceptions\WordPressEnvironmentException;
use Omega\Database\Database;
use Omega\Database\DynamicModel;
use Omega\Database\Migrations\Migrator;
use Omega\Database\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\WordPressRuntime;

use function array_key_first;
use function count;
use function defined;
use function define;
use function Omega\Application\slash;
use function str_contains;

#[CoversClass(Database::class)]
#[CoversClass(DynamicModel::class)]
final class DatabaseTest extends DatabaseTestCase
{
    public function testConstructorResolvesTheMigrator(): void
    {
        $database = new Database($this->pluginApp());

        $this->assertInstanceOf(Migrator::class, $database->migrator());
    }

    public function testConstructorThrowsWithoutTheWordPressDatabase(): void
    {
        $previous = $GLOBALS['wpdb'];
        $GLOBALS['wpdb'] = null;

        try {
            $this->expectException(WordPressEnvironmentException::class);
            $this->expectExceptionMessage('The WordPress database object ($wpdb) is not available.');

            new Database($this->pluginApp());
        } finally {
            $GLOBALS['wpdb'] = $previous;
        }
    }

    public function testGetTableNameAppliesTheWordPressPrefix(): void
    {
        $this->assertSame('wp_posts', Database::getTableName('posts'));
    }

    public function testGetTableNameAppliesAnExtraPrefix(): void
    {
        $this->assertSame('wp_my_posts', Database::getTableName('posts', 'my_'));
    }

    public function testCreateOrUpdateTableDelegatesTheStatementToDbDelta(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', slash(path: __DIR__ . '/../fixtures/app/plugin/wp/'));
        }

        Database::createOrUpdateTable('posts', [
            'title' => 'varchar(255)',
            'body' => 'text',
        ]);

        $statements = WordPressRuntime::$dbDeltaStatements;

        $this->assertCount(1, $statements);

        $statement = $statements[0] ?? '';

        $this->assertStringContainsString('CREATE TABLE wp_posts', $statement);
        $this->assertStringContainsString('title varchar(255)', $statement);
        $this->assertStringContainsString('body text', $statement);
        $this->assertStringContainsString('PRIMARY KEY', $statement);
        $this->assertStringContainsString('DEFAULT CHARACTER SET utf8mb4', $statement);
    }

    public function testTableExistsDetectsAnExistingTable(): void
    {
        $this->wpdb()->varValue = 'wp_posts';

        $this->assertTrue(Database::tableExists('posts'));
        $this->assertContains("SHOW TABLES LIKE 'posts'", $this->wpdb()->queries);
    }

    public function testTableExistsDetectsAMissingTable(): void
    {
        $this->wpdb()->varValue = null;

        $this->assertFalse(Database::tableExists('posts'));
    }

    public function testTableReturnsAQueryBuilderForTheTable(): void
    {
        $builder = Database::table('posts');

        $this->assertInstanceOf(QueryBuilder::class, $builder);
    }

    public function testPrepareReturnsThePreparedStatement(): void
    {
        $database = new Database($this->pluginApp());

        $prepared = $database->prepare('SELECT * FROM wp_posts WHERE id = %d', 7);

        $this->assertSame('SELECT * FROM wp_posts WHERE id = 7', $prepared);
    }

    public function testPrepareReturnsAnEmptyStringWhenPreparationFails(): void
    {
        $database = new Database($this->pluginApp());
        $this->wpdb()->failPrepare = true;

        $this->assertSame('', $database->prepare('SELECT * FROM wp_posts'));
    }

    public function testQueryDelegatesToTheWordPressDatabase(): void
    {
        $database = new Database($this->pluginApp());

        $this->assertSame(1, $database->query('SELECT 1'));
        $this->assertContains('SELECT 1', $this->wpdb()->queries);
    }

    public function testGetResultsReturnsTheConfiguredRows(): void
    {
        $database = new Database($this->pluginApp());
        $this->wpdb()->results = [(object) ['id' => 1]];

        $results = $database->getResults('SELECT * FROM wp_posts');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    public function testGetResultsReturnsNullWithoutRows(): void
    {
        $database = new Database($this->pluginApp());

        $this->assertSame([], $database->getResults('SELECT * FROM wp_posts'));
    }

    public function testGetVarReturnsTheConfiguredValue(): void
    {
        $database = new Database($this->pluginApp());
        $this->wpdb()->varValue = 'wp_posts';

        $this->assertSame('wp_posts', $database->getVar('SHOW TABLES'));
    }

    public function testDeleteDelegatesToTheWordPressDatabase(): void
    {
        $database = new Database($this->pluginApp());

        $this->assertSame(1, $database->delete('wp_posts', ['id' => 3]));
        $this->assertSame([['wp_posts', ['id' => 3]]], $this->wpdb()->deletes);
    }

    public function testInsertReturnsTheGeneratedInsertId(): void
    {
        $this->assertSame(1, Database::insert('wp_posts', ['title' => 'Hello']));
        $this->assertSame([['wp_posts', ['title' => 'Hello']]], $this->wpdb()->inserts);
    }

    public function testInsertReturnsFalseWhenTheInsertFails(): void
    {
        $this->wpdb()->failNext = true;

        $this->assertFalse(Database::insert('wp_posts', ['title' => 'Hello']));
    }

    public function testUpdateReturnsTheAffectedRowCount(): void
    {
        $database = new Database($this->pluginApp());
        $this->wpdb()->affectedRows = 2;

        $updated = $database->update('wp_posts', ['title' => 'New'], ['id' => 3]);

        $this->assertSame(2, $updated);
        $this->assertSame([['wp_posts', ['title' => 'New'], ['id' => 3]]], $this->wpdb()->updates);
    }

    public function testUpdateReturnsFalseWhenTheUpdateFails(): void
    {
        $database = new Database($this->pluginApp());
        $this->wpdb()->failNext = true;

        $this->assertFalse($database->update('wp_posts', ['title' => 'New'], ['id' => 3]));
    }

    public function testInsertMultipleReturnsFalseWithoutRows(): void
    {
        $database = new Database($this->pluginApp());

        $this->assertFalse($database->insertMultiple('wp_posts', []));
        $this->assertSame(0, count($this->wpdb()->queries));
    }

    public function testInsertMultipleBuildsABatchInsert(): void
    {
        $database = new Database($this->pluginApp());

        $inserted = $database->insertMultiple('wp_posts', [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ]);

        $queries = $this->wpdb()->queries;

        $this->assertSame(1, $inserted);
        $this->assertTrue(str_contains($queries[0], 'INSERT INTO wp_posts (id, title) VALUES (%s, %s), (%s, %s)'));
        $this->assertTrue(
            str_contains($queries[1], "INSERT INTO wp_posts (id, title) VALUES (1, 'First'), (2, 'Second')")
        );
    }

    public function testInsertMultipleAcceptsASingleRow(): void
    {
        $database = new Database($this->pluginApp());

        $inserted = $database->insertMultiple('wp_posts', [['id' => 1]]);

        $queries = $this->wpdb()->queries;

        $this->assertSame(1, $inserted);
        $this->assertTrue(str_contains($queries[0], 'INSERT INTO wp_posts (id) VALUES (%s)'));
        $this->assertTrue(str_contains($queries[1], 'INSERT INTO wp_posts (id) VALUES (1)'));
    }

    public function testInsertMultipleAcceptsASingleRowWithSeveralColumns(): void
    {
        $database = new Database($this->pluginApp());

        $inserted = $database->insertMultiple('wp_posts', [['id' => 4, 'title' => 'Only']]);

        $queries = $this->wpdb()->queries;

        $this->assertSame(1, $inserted);
        $this->assertTrue(str_contains($queries[0], 'INSERT INTO wp_posts (id, title) VALUES (%s, %s)'));
        $this->assertTrue(str_contains($queries[1], 'INSERT INTO wp_posts (id, title) VALUES (4, \'Only\')'));
    }

    public function testInsertMultipleAcceptsSeveralRowsWithASingleColumn(): void
    {
        $database = new Database($this->pluginApp());

        $inserted = $database->insertMultiple('wp_posts', [
            ['id' => 1],
            ['id' => 2],
        ]);

        $queries = $this->wpdb()->queries;

        $this->assertSame(1, $inserted);
        $this->assertTrue(str_contains($queries[0], 'INSERT INTO wp_posts (id) VALUES (%s), (%s)'));
        $this->assertTrue(str_contains($queries[1], 'INSERT INTO wp_posts (id) VALUES (1), (2)'));
    }

    public function testInsertMultipleAcceptsRowsWithoutColumns(): void
    {
        $database = new Database($this->pluginApp());

        $inserted = $database->insertMultiple('wp_posts', [[]]);

        $queries = $this->wpdb()->queries;

        $this->assertSame(1, $inserted);
        $this->assertTrue(str_contains($queries[0], 'INSERT INTO wp_posts () VALUES ()'));
    }
}
