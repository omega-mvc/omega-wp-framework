<?php

declare(strict_types=1);

namespace Tests\Console\Commands\Fixtures;

use Omega\Console\Attribute\AsCommand;
use Omega\Console\Commands\AbstractMakeCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Command fixture extending AbstractMakeCommand without a Make attribute.
 *
 */
#[AsCommand(
    name: 'plain:make',
    description: 'Plain make command.'
)]
class NoMakeCommand extends AbstractMakeCommand
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the file to create');
    }
}