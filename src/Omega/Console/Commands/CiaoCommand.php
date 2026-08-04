<?php

declare(strict_types=1);

namespace Omega\Console\Commands;

use Omega\Console\AbstractCommand;
use Omega\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'ciao',
    description: 'Prints a hello world message.'
)]
class CiaoCommand extends AbstractCommand
{
    /**
     * Execute the command logic.
     *
     * @return int Exit status code.
     */
    public function __invoke(): int
    {
        $this->output->writeln('Ciao Mondo!.');

        return self::SUCCESS;
    }
}
