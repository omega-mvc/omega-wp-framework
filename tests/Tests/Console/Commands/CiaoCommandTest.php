<?php

declare(strict_types=1);

namespace Tests\Console\Commands;

use Omega\Console\Commands\CiaoCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

covers(CiaoCommand::class);

it('prints a hello world message', function (): void {
    $result = (new CommandTester(new CiaoCommand()))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Ciao Mondo!.');
});
