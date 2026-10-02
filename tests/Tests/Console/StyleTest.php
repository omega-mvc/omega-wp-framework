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

namespace Tests\Console;

use Omega\Console\Style;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Console\Fixtures\StreamFactory;

/**
 * Tests the Style console output helper.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Style::class)]
final class StyleTest extends TestCase
{
    /**
     * Test a single line is written indented.
     */
    public function testWritesAnIndentedSingleLine(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->writeln('alpha');

        $this->assertSame('  alpha' . PHP_EOL, $output->fetch());
    }

    /**
     * Test multiple lines are written keeping empty lines unindented.
     */
    public function testWritesMultipleLinesKeepingEmptyLinesUnindented(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->writeln(['alpha', '', 'beta']);

        $this->assertSame('  alpha' . PHP_EOL . PHP_EOL . '  beta' . PHP_EOL, $output->fetch());
    }

    /**
     * Test trailing line endings are trimmed before indenting.
     */
    public function testTrimsTrailingLineEndingsBeforeIndenting(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->writeln("alpha\r\n");

        $this->assertSame('  alpha' . PHP_EOL, $output->fetch());
    }

    /**
     * Test all styled message blocks are rendered.
     */
    public function testRendersAllStyledMessageBlocks(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->success('done');
        $style->error('bad');
        $style->warning('careful');
        $style->comment('see note');
        $style->note('remember');
        $style->info('known');

        $display = $output->fetch();

        $this->assertStringContainsString('SUCCESS  done', $display);
        $this->assertStringContainsString('ERROR  bad', $display);
        $this->assertStringContainsString('WARNING  careful', $display);
        $this->assertStringContainsString('COMMENT see note', $display);
        $this->assertStringContainsString('NOTE  remember', $display);
        $this->assertStringContainsString('INFO  known', $display);
    }

    /**
     * Test iterable messages are accepted in styled blocks.
     */
    public function testAcceptsIterableMessagesInStyledBlocks(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->success(['first', 'second']);

        $display = $output->fetch();

        $this->assertStringContainsString('first', $display);
        $this->assertStringContainsString('second', $display);
    }

    /**
     * Test a blank line is inserted between blocks that end with content.
     */
    public function testInsertsABlankLineBetweenBlocksThatEndWithContent(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->writeln('plain');
        $style->success('after');

        $this->assertStringContainsString('  plain' . PHP_EOL . PHP_EOL . '   SUCCESS', $output->fetch());
    }

    /**
     * Test titles, sections and text are rendered.
     */
    public function testRendersTitlesSectionsAndText(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->title('Title');
        $style->section('Body');
        $style->text('hidden note');
        $style->text(['visible line']);

        $display = $output->fetch();

        $this->assertStringContainsString('Title', $display);
        $this->assertStringContainsString('== Body ==', $display);
        $this->assertStringContainsString('  hidden note', $display);
        $this->assertStringContainsString('  visible line', $display);
    }

    /**
     * Test the answer provided by the user is returned.
     */
    public function testReturnsTheAnswerProvidedByTheUser(): void
    {
        $input = new ArrayInput([]);
        $input->setStream(StreamFactory::streamWith("Ada\n"));
        $output = new BufferedOutput();
        $style = new Style($input, $output);

        $this->assertSame('Ada', $style->ask('Your name?', 'default'));
    }

    /**
     * Test a null default is used when the default is not a string.
     */
    public function testUsesANullDefaultWhenTheDefaultIsNotAString(): void
    {
        $input = new ArrayInput([]);
        $input->setStream(StreamFactory::streamWith("Ada\n"));
        $output = new BufferedOutput();
        $style = new Style($input, $output);

        $this->assertSame('Ada', $style->ask('Your name?', 7));
    }

    /**
     * Test the prompt confirms when the user answers yes.
     */
    public function testConfirmsWhenTheUserAnswersYes(): void
    {
        $input = new ArrayInput([]);
        $input->setStream(StreamFactory::streamWith("yes\n"));
        $output = new BufferedOutput();
        $style = new Style($input, $output);

        $this->assertTrue($style->confirm('Continue?'));
    }

    /**
     * Test the default is used as fallback when no answer is provided.
     */
    public function testFallsBackToTheDefaultWhenNoAnswerIsProvided(): void
    {
        $input = new ArrayInput([]);
        $input->setStream(StreamFactory::streamWith(''));
        $output = new BufferedOutput();
        $style = new Style($input, $output);

        $this->assertFalse($style->confirm('Continue?', false));
    }

    /**
     * Test the requested number of blank lines is written.
     */
    public function testWritesTheRequestedNumberOfBlankLines(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->newLine(2);

        $this->assertSame(PHP_EOL . PHP_EOL, $output->fetch());
    }

    /**
     * Test a progress bar is created with a message.
     */
    public function testCreatesAProgressBarWithAMessage(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $bar = $style->progressBar(10, 'Working');

        $this->assertSame(10, $bar->getMaxSteps());
        $this->assertSame('Working', $bar->getMessage());

        $output->fetch();
    }

    /**
     * Test a progress bar is created without a message.
     */
    public function testCreatesAProgressBarWithoutAMessage(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $bar = $style->progressBar(0);

        $this->assertSame(0, $bar->getMaxSteps());
        $this->assertNull($bar->getMessage());

        $output->fetch();
    }

    /**
     * Test nothing is written when given an empty messages array.
     */
    public function testWritesNothingWhenGivenAnEmptyMessagesArray(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->writeln([]);

        $this->assertSame('', $output->fetch());
    }

    /**
     * Test an empty string line is not indented.
     */
    public function testDoesNotIndentAnEmptyStringLine(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->writeln('');

        $this->assertSame(PHP_EOL, $output->fetch());
    }

    /**
     * Test an empty iterable message is rendered in a styled block.
     */
    public function testRendersAnEmptyIterableMessageInAStyledBlock(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->success([]);

        $this->assertSame('   SUCCESS  ' . PHP_EOL . PHP_EOL, $output->fetch());
    }

    /**
     * Test no lines are written when given a traversable message.
     */
    public function testWritesNoLinesWhenGivenATraversableMessage(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->writeln((static function (): iterable {
            yield 'first';
            yield 'second';
        })());

        $this->assertSame('', $output->fetch());
    }

    /**
     * Test an empty styled block is rendered for a traversable message.
     */
    public function testRendersAnEmptyStyledBlockForATraversableMessage(): void
    {
        $output = new BufferedOutput();
        $style = new Style(new ArrayInput([]), $output);

        $style->success((static function (): iterable {
            yield 'first';
            yield 'second';
        })());

        $this->assertSame('   SUCCESS  ' . PHP_EOL . PHP_EOL, $output->fetch());
    }
}
