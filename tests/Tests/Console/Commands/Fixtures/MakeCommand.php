<?php

declare(strict_types=1);

/**
 * Part of Omega - Tests\Console\Commands\Fixtures.
 *
 * @link      https://omega-mvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Axel Magold (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

namespace App\Console\Commands;

use Omega\Console\AbstractCommand;
use Omega\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:custom',
    description: 'Application custom command.'
)]
class MakeCommand extends AbstractCommand
{
    public function __invoke(): int
    {
        return self::SUCCESS;
    }
}