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

namespace Omega\Console\Traits;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;

use function array_reduce;
use function max;
use function sprintf;
use function str_repeat;

/**
 * Provides helper methods for rendering aligned console output.
 *
 * This trait contains reusable utilities for measuring the visible width
 * of formatted console strings and producing consistently aligned output
 * regardless of ANSI decorations or terminal size.
 *
 * It is intended for console commands and output components that require
 * right-aligned messages, multi-column layouts, or width-aware formatting.
 *
 * @category   Omega
 * @package    Console
 * @subpackage Traits
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
trait InteractsWithConsoleOutputTrait
{
	/**
	 * Cached terminal width.
	 *
	 * The width is resolved only once and reused for subsequent output
	 * operations during the current command execution.
	 *
	 * @var int|null
	 */
	private ?int $terminalWidth = null;

	/**
	 * Returns the current terminal width.
	 *
	 * The value is cached after the first lookup to avoid repeatedly
	 * querying the terminal dimensions.
	 *
	 * @return int The terminal width in characters.
	 */
	protected function getTerminalWidth(): int
	{
		return $this->terminalWidth ??= new Terminal()->getWidth();
	}

	/**
	 * Returns the visible width of a formatted string.
	 *
	 * ANSI formatting tags and terminal decorations are removed before
	 * calculating the string length, ensuring the returned value reflects
	 * only the characters actually displayed.
	 *
	 * @param string $string The formatted string.
	 * @return int The visible width of the string.
	 */
	protected function getVisibleWidth(string $string): int
	{
		return Helper::width(
			Helper::removeDecoration(
				$this->output->getFormatter(),
				$string
			)
		);
	}

	/**
	 * Returns the largest visible width from a collection of values.
	 *
	 * Each item is converted to a string before its visible width is
	 * calculated.
	 *
	 * @param array<int|string, mixed> $items The values to measure.
	 * @return int The maximum visible width, or zero if the array is empty.
	 */
	protected function getVisibleMaxWidth(array $items): int
	{
		if (empty($items)) {
			return 0;
		}

		return array_reduce($items, function ($max, $item) {
			return max($max, $this->getVisibleWidth((string) $item));
		}, 0);
	}

	/**
	 * Writes a message aligned to the right side of the terminal.
	 *
	 * The alignment is based on the visible width of the message, ignoring
	 * any formatting tags or ANSI escape sequences.
	 *
	 * @param string $message The message to display.
	 * @param int $margin Number of spaces to leave between the message and
	 *                    the right edge of the terminal.
	 * @return void
	 */
	protected function writeRight(string $message, int $margin = 2): void
	{
		$visualWidth = $this->getVisibleWidth($message);

		$width = $this->getTerminalWidth();

		$spacesCount = max(0, $width - $visualWidth - $margin);

		$this->output->writeln(
			str_repeat(' ', $spacesCount) . $message
		);
	}

	/**
	 * Writes two values on a single line separated by a dotted filler.
	 *
	 * The left value is rendered near the beginning of the line while the
	 * right value is aligned toward the end. The available space between
	 * them is filled with gray dots.
	 *
	 * @param string $left The left column content.
	 * @param string $right The right column content.
	 * @param int $leftMargin Number of leading spaces before the left column.
	 * @param int $rightMargin Number of trailing spaces after the right column.
	 * @return void
	 */
	protected function componentsTwoColumns(
		string $left,
		string $right,
		int $leftMargin = 2,
		int $rightMargin = 2
	): void {
		$width = $this->getTerminalWidth();

		$leftVisible  = $this->getVisibleWidth($left);
		$rightVisible = $this->getVisibleWidth($right);

		$dotsCount = max(
			2,
			$width - $leftVisible - $rightVisible - $leftMargin - $rightMargin - 2
		);

		$dots = "<fg=gray>" . str_repeat('.', $dotsCount) . "</>";

		$this->output->writeln( sprintf(
			'%s%s %s %s%s',
			str_repeat(' ', $leftMargin),
			$left,
			$dots,
			$right,
			str_repeat(' ', $rightMargin)
		));
	}
}
