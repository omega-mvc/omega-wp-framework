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

use Omega\Console\Traits\InteractsWithConsoleOutputTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Console\Fixtures\OutputStyler;

use function rtrim;
use function str_repeat;
use function strlen;

/**
 * Tests the InteractsWithConsoleOutputTrait layout helpers.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversTrait(InteractsWithConsoleOutputTrait::class)]
final class InteractsWithConsoleOutputTest extends TestCase
{
    /**
     * Test the terminal width is returned.
     */
    public function testReturnsTheTerminalWidth(): void
    {
        $styler = new OutputStyler(new BufferedOutput());

        $this->assertGreaterThan(0, $styler->terminalWidth());
    }

    /**
     * Test the visible width ignores formatting tags.
     */
    public function testCalculatesTheVisibleWidthIgnoringFormattingTags(): void
    {
        $styler = new OutputStyler(new BufferedOutput());

        $this->assertSame(5, $styler->visibleWidth('plain'));
        $this->assertSame(6, $styler->visibleWidth('<info>tagged</info>'));
        $this->assertSame(3, $styler->visibleWidth("\033[31mred\033[0m"));
        $this->assertSame(0, $styler->visibleWidth(''));
    }

    /**
     * Test the largest visible width of a collection is returned.
     */
    public function testReturnsTheLargestVisibleWidthOfACollection(): void
    {
        $styler = new OutputStyler(new BufferedOutput());

        $this->assertSame(6, $styler->visibleMaxWidth(['foo', 'longer', '<info>x</info>']));
        $this->assertSame(0, $styler->visibleMaxWidth([]));
    }

    /**
     * Test a message is aligned to the right edge with the given margin.
     */
    public function testAlignsAMessageToTheRightEdgeWithTheGivenMargin(): void
    {
        $output = new BufferedOutput();
        $styler = new OutputStyler($output);

        $styler->printRight('bye');
        $display = $output->fetch();

        $this->assertStringEndsWith(PHP_EOL, $display);
        $this->assertStringEndsWith('bye', rtrim($display, PHP_EOL));
        $this->assertSame($styler->terminalWidth() - 2, strlen(rtrim($display, PHP_EOL)));
    }

    /**
     * Test a custom right margin is respected.
     */
    public function testRespectsACustomRightMargin(): void
    {
        $output = new BufferedOutput();
        $styler = new OutputStyler($output);

        $styler->printRight('bye', 6);
        $display = rtrim($output->fetch(), PHP_EOL);

        $this->assertStringEndsWith('bye', $display);
        $this->assertSame($styler->terminalWidth() - 6, strlen($display));
    }

    /**
     * Test no padding is applied when the message is wider than the terminal.
     */
    public function testDoesNotPadWhenTheMessageIsWiderThanTheTerminal(): void
    {
        $output = new BufferedOutput();
        $styler = new OutputStyler($output);
        $message = str_repeat('x', $styler->terminalWidth() + 100);

        $styler->printRight($message);

        $this->assertSame($message, rtrim($output->fetch(), PHP_EOL));
    }

    /**
     * Test two columns are written separated by a dotted filler.
     */
    public function testWritesTwoColumnsSeparatedByDottedFiller(): void
    {
        $output = new BufferedOutput();
        $styler = new OutputStyler($output);

        $styler->printColumns('Config', 'Done');

        $display = rtrim($output->fetch(), PHP_EOL);

        $this->assertStringStartsWith('  Config ', $display);
        $this->assertStringEndsWith(' Done  ', $display);
        $this->assertStringContainsString(str_repeat('.', 10), $display);
    }

    /**
     * Test at least two dots are kept when the content fills the line.
     */
    public function testKeepsAtLeastTwoDotsWhenTheContentFillsTheLine(): void
    {
        $output = new BufferedOutput();
        $styler = new OutputStyler($output);
        $left = str_repeat('l', 60);
        $right = str_repeat('r', 60);

        $styler->printColumns($left, $right);

        $display = rtrim($output->fetch(), PHP_EOL);

        $this->assertStringStartsWith('  ' . $left, $display);
        $this->assertStringEndsWith($right . '  ', $display);
        $this->assertStringContainsString('..', $display);
    }
}
