<?php

/**
 * Part of Omega - Tests Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Console\Traits;

use Omega\Console\Traits\InteractWithFilesystemTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Tests\Console\Fixtures\FilesystemProbe;

/**
 * Tests the InteractWithFilesystemTrait glob helper.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversTrait(InteractWithFilesystemTrait::class)]
final class InteractWithFilesystemTest extends TestCase
{
    /**
     * Test an empty list is returned when the directory does not exist.
     */
    public function testReturnsAnEmptyListWhenTheDirectoryDoesNotExist(): void
    {
        $probe = new FilesystemProbe();

        $this->assertSame([], $probe->find('/no/such/directory', '*'));
    }

    /**
     * Test files are matched by a single pattern.
     */
    public function testMatchesFilesByASinglePattern(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', '*.txt');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('a.txt', $files[0]);
    }

    /**
     * Test files are matched by multiple patterns.
     */
    public function testMatchesFilesByMultiplePatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', ['*.php', '*.log']);

        $this->assertCount(2, $files);
    }

    /**
     * Test every file is matched with the default pattern.
     */
    public function testMatchesEveryFileWithTheDefaultPattern(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files');

        $this->assertCount(3, $files);
    }

    /**
     * Test files matching the given patterns are excluded.
     */
    public function testExcludesFilesMatchingTheGivenPatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', '*', ['*.log']);

        $this->assertCount(2, $files);
        $this->assertNotContains(__DIR__ . '/../Fixtures/Files/b.log', $files);
    }

    /**
     * Test every file is matched with zero patterns.
     */
    public function testMatchesEveryFileWithZeroPatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', []);

        $this->assertCount(3, $files);
    }

    /**
     * Test files matching multiple patterns are excluded.
     */
    public function testExcludesFilesMatchingMultiplePatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', '*', ['*.txt', '*.log']);

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('c.php', $files[0]);
    }

    /**
     * Test files are excluded when matching with zero patterns.
     */
    public function testExcludesFilesWhenMatchingWithZeroPatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', [], ['*.log']);

        $this->assertCount(2, $files);
        $this->assertNotContains(__DIR__ . '/../Fixtures/Files/b.log', $files);
    }

    /**
     * Test every file is excluded when matching with zero patterns.
     */
    public function testExcludesEveryFileWhenMatchingWithZeroPatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', [], ['*.txt', '*.log']);

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('c.php', $files[0]);
    }

    /**
     * Test a pattern is excluded from multiple patterns.
     */
    public function testExcludesAPatternFromMultiplePatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', ['*.txt', '*.log'], ['*.txt']);

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('b.log', $files[0]);
    }

    /**
     * Test every pattern is excluded from multiple patterns.
     */
    public function testExcludesEveryPatternFromMultiplePatterns(): void
    {
        $probe = new FilesystemProbe();
        $files = $probe->find(__DIR__ . '/../Fixtures/Files', ['*.txt', '*.log'], ['*.txt', '*.log']);

        $this->assertCount(0, $files);
    }
}
