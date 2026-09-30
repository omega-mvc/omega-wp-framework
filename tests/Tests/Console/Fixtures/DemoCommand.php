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
use Symfony\Component\Console\Input\InputOption;

use function is_string;

/**
 * Fully configured command exercising arguments and options.
 */
#[AsCommand(
    name: 'demo:hello',
    description: 'Demonstrates a command with arguments and options.',
    aliases: ['demo:h'],
    arguments: [
        'name' => [InputArgument::OPTIONAL, 'Your name', 'World'],
    ],
    options: [
        'greet' => ['g', InputOption::VALUE_NONE, 'Greet the user'],
    ],
)]
class DemoCommand extends AbstractCommand
{
    public function __invoke(): int
    {
        $name = $this->getArgument('name');

        $this->output->writeln(
            'hello=' . (is_string($name) ? $name : '') . '|' . var_export($this->getOption('greet'), true)
        );

        return self::SUCCESS;
    }
}
