<?php

/**
 * Part of Omega - Tests\Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Tests\Console\Fixtures;

use Omega\Console\Traits\InteractsWithConsoleOutputTrait;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test probe exposing the protected methods of InteractsWithConsoleOutputTrait.
 */
class OutputStyler
{
    use InteractsWithConsoleOutputTrait;

    public OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function terminalWidth(): int
    {
        return $this->getTerminalWidth();
    }

    public function visibleWidth(string $string): int
    {
        return $this->getVisibleWidth($string);
    }

    /**
     * @param array<int|string, string> $items
     */
    public function visibleMaxWidth(array $items): int
    {
        return $this->getVisibleMaxWidth($items);
    }

    public function printRight(string $message, int $margin = 2): void
    {
        $this->writeRight($message, $margin);
    }

    public function printColumns(string $left, string $right, int $leftMargin = 2, int $rightMargin = 2): void
    {
        $this->componentsTwoColumns($left, $right, $leftMargin, $rightMargin);
    }
}
