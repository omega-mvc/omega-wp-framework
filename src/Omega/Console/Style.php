<?php

/**
 * Part of Omega - Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Console;

use Override;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Provides a customized console style for Omega commands.
 *
 * This class extends Symfony's {@see SymfonyStyle} by introducing a
 * consistent visual appearance across all console output. It automatically
 * applies indentation, spacing, and simplified message rendering for
 * informational blocks, questions, and progress bars.
 *
 * Unlike the default Symfony style, this implementation keeps track of
 * blank lines to avoid duplicated spacing while ensuring that each visual
 * block is clearly separated from the previous output.
 *
 * @category  Omega
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Style extends SymfonyStyle
{
    /** @var string Prefix applied to every rendered output line. */
    protected string $indent = '  ';

    /** @var string Separator used between message labels and their content. */
    protected string $separator = ' ';

    /**
     * Indicates whether the previously written line is empty.
     *
     * This flag is used to automatically insert spacing between logical
     * output blocks without producing duplicate blank lines.
     *
     * @var bool
     */
    protected bool $isLastLineEmpty = true;

    /** @var OutputInterface Console output instance used by custom output components. */
    private OutputInterface $output;

    /**
     * Creates a new styled console output instance.
     *
     * @param InputInterface $input The console input.
     * @param OutputInterface $output The console output.
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        parent::__construct($input, $output);
    }

    /**
     * Writes one or more messages to the console.
     *
     * Every non-empty line is automatically indented before being written.
     *
     * @param string|array<int|string, string> $messages The message or messages to write.
     * @param int $type The output verbosity type.
     * @return void
     */
    #[Override]
    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        foreach ((array) $messages as $message) {
            $message = (string) $message;

            $message = rtrim($message, "\r\n");

            if ($message !== '') {
                $message = $this->indent . $message;
                $this->isLastLineEmpty = false;
            } else {
                $this->isLastLineEmpty = true;
            }

            parent::writeln($message, $type);
        }
    }

    /**
     * Displays a success message.
     *
     * @param string|array<int|string, string> $message The message to display.
     * @return void
     */
    #[Override]
    public function success(string|iterable $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln('<bg=green;fg=white> SUCCESS </>' . $this->separator . $message);

        $this->newLine(); // 👈 riga vuota garantita
    }

    /**
     * Displays an error message.
     *
     * @param string|array<int|string, string> $message The message to display.
     * @return void
     */
    #[Override]
    public function error(string|iterable $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln('<bg=red;fg=white> ERROR </>' . $this->separator . $message);

        $this->newLine(); // 👈 riga vuota garantita
    }

    /**
     * Displays a warning message.
     *
     * @param string|array<int|string, string> $message The message to display.
     * @return void
     */
    #[Override]
    public function warning(string|iterable $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln('<bg=yellow;fg=black> WARNING </>' . $this->separator . $message);

        $this->newLine(); // 👈 riga vuota garantita
    }

    /**
     * Displays a comment message.
     *
     * @param string|array<int|string, string> $message The message to display.
     * @return void
     */
    #[Override]
    public function comment(string|iterable $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln('<fg=yellow>COMMENT</>' . $this->separator . $message);

        $this->newLine();
    }

    /**
     * Displays a note message.
     *
     * @param string|array<int|string, string> $message The message to display.
     * @return void
     */
    #[Override]
    public function note(string|iterable $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln('<bg=cyan;fg=black> NOTE </>' . $this->separator . $message);

        $this->newLine();
    }

    /**
     * Displays an informational message.
     *
     * @param string|array<int|string, string> $message The message to display.
     * @return void
     */
    #[Override]
    public function info(string|iterable $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln('<bg=blue;fg=white> INFO </>' . $this->separator . $message);

        $this->newLine();
    }

    /**
     * Displays a formatted title.
     *
     * @param string $message The title text.
     * @return void
     */
    #[Override]
    public function title(string $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln("<options=bold;underscore>$message</>");

        $this->newLine();
    }

    /**
     * Displays a formatted section heading.
     *
     * @param string $message The section title.
     * @return void
     */
    #[Override]
    public function section(string $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln("<fg=cyan;options=bold>== $message ==</>");

        $this->newLine();
    }

    /**
     * Displays plain text.
     *
     * @param string|array<int|string, string> $message The text to display.
     * @return void
     */
    #[Override]
    public function text(string|array $message): void
    {
        $message = $this->processMessage($message);

        $this->ensureTopSpacing();

        $this->writeln($message);

        $this->newLine();
    }

    /**
     * Prompts the user for textual input.
     *
     * @param string $question The question displayed to the user.
     * @param mixed $default The default value.
     * @param callable|null $validator Optional input validator.
     * @return mixed The user input.
     */
    #[Override]
    public function ask(string $question, $default = null, $validator = null): mixed
    {
        $this->ensureTopSpacing();

        $question = $this->indent . "<fg=cyan;options=bold>?</> $question";

        $result = parent::ask($question, $default, $validator);

        $this->isLastLineEmpty = false;

        return $result;
    }

    /**
     * Prompts the user for confirmation.
     *
     * @param string $question The confirmation question.
     * @param bool $default The default answer.
     * @return bool True if confirmed, otherwise false.
     */
    #[Override]
    public function confirm(string $question, bool $default = true): bool
    {
        $this->ensureTopSpacing();

        $question = $this->indent . "<fg=cyan;options=bold>?</> $question";

        $result = parent::confirm($question, $default);

        $this->isLastLineEmpty = false;

        return $result;
    }

    /**
     * Normalizes a message into a printable string.
     *
     * Iterable messages are converted into a single string separated by
     * line breaks.
     *
     * @param string|array<int|string, string> $message The message to normalize.
     * @return string The normalized message.
     */
    private function processMessage(string|iterable $message): string
    {
        if (is_iterable($message)) {
            return implode(PHP_EOL, array_map(
                fn($m) => (string) $m,
                (array) $message
            ));
        }

        return (string) $message;
    }

    /**
     * Ensures that the next output block starts after a blank line.
     *
     * @return void
     */
    protected function ensureTopSpacing(): void
    {
        if (!$this->isLastLineEmpty) {
            parent::newLine();
            $this->isLastLineEmpty = true;
        }
    }

    /**
     * Writes one or more blank lines.
     *
     * @param int $count Number of blank lines.
     * @return void
     */
    #[Override]
    public function newLine(int $count = 1): void
    {
        parent::newLine($count);
        $this->isLastLineEmpty = true;
    }

    /**
     * Creates and configures a styled progress bar.
     *
     * The returned progress bar uses Omega's custom formatting and visual
     * appearance while remaining fully compatible with Symfony's
     * {@see ProgressBar}.
     *
     * @param int $max The maximum number of steps.
     * @param string $message Optional message displayed below the progress bar.
     * @return ProgressBar The configured progress bar instance.
     */
    public function progressBar(int $max = 0, string $message = ''): ProgressBar
    {
        $this->ensureTopSpacing();

        $progressBar = new ProgressBar($this->output, $max);

        $progressBar->setFormat(
            $this->indent . " %current%/%max% [%bar%] %percent:3s%% \n" .
            $this->indent . " <fg=gray>%message%</>"
        );

        $progressBar->setBarCharacter('<fg=green>━</>');
        $progressBar->setEmptyBarCharacter('<fg=gray>─</>');
        $progressBar->setProgressCharacter('<fg=green>❯</>');

        if ($message) {
            $progressBar->setMessage($message);
        }

        $this->isLastLineEmpty = false;

        return $progressBar;
    }
}
