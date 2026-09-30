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

use Omega\Console\AbstractCommand;
use Omega\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Argument configuration with a non-string description that must throw.
 */
#[AsCommand(
    name: 'demo:bad-arg-desc',
    arguments: [
        'name' => [InputArgument::REQUIRED, 123],
    ],
)]
class BadArgumentDescriptionCommand extends AbstractCommand
{
    public function __invoke(): int
    {
        return self::SUCCESS;
    }
}
