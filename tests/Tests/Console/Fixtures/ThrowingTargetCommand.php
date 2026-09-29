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
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Command that always throws, exercised through AbstractCommand::call().
 */
#[AsCommand(
    name: 'demo:target',
    arguments: [
        'name' => [InputArgument::OPTIONAL, 'The name', 'World'],
    ],
)]
class ThrowingTargetCommand extends AbstractCommand
{
    public function __invoke(): int
    {
        throw new RuntimeException('boom');
    }
}
