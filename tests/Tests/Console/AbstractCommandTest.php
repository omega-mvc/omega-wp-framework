<?php

declare(strict_types=1);

namespace Tests\Console;

use Omega\Application\Application;
use Omega\Console\AbstractCommand;
use Omega\Console\ConsoleBranding;
use Omega\Console\Exceptions\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Fixtures\BadArgumentCountCommand;
use Tests\Console\Fixtures\BadArgumentDescriptionCommand;
use Tests\Console\Fixtures\BadArgumentModeCommand;
use Tests\Console\Fixtures\BadOptionCountCommand;
use Tests\Console\Fixtures\BadOptionDescriptionCommand;
use Tests\Console\Fixtures\BadOptionModeCommand;
use Tests\Console\Fixtures\BadOptionShortcutCommand;
use Tests\Console\Fixtures\BadSuggestedValuesCommand;
use Tests\Console\Fixtures\CallerCommand;
use Tests\Console\Fixtures\DemoCommand;
use Tests\Console\Fixtures\MinimalCommand;
use Tests\Console\Fixtures\NoAttributeCommand;
use Tests\Console\Fixtures\NullReturnCommand;
use Tests\Console\Fixtures\SuggestedArrayCommand;
use Tests\Console\Fixtures\TargetCommand;
use Tests\Console\Fixtures\ThrowingTargetCommand;

covers(AbstractCommand::class);

it('configures the command from the AsCommand attribute', function (): void {
    $command = new DemoCommand();

    expect($command->getName())->toBe('demo:hello')
        ->and($command->getDescription())->toBe('Demonstrates a command with arguments and options.')
        ->and($command->getAliases())->toBe(['demo:h'])
        ->and($command->isHidden())->toBeFalse()
        ->and($command->getDefinition()->hasArgument('name'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('greet'))->toBeTrue()
        ->and($command->getDefinition()->getArgument('name')->getDefault())->toBe('World');
});

it('applies the hidden flag and empty definitions', function (): void {
    $command = new MinimalCommand();

    expect($command->getName())->toBe('demo:minimal')
        ->and($command->isHidden())->toBeTrue()
        ->and($command->getDescription())->toBe('')
        ->and($command->getDefinition()->getArguments())->toBe([])
        ->and($command->getDefinition()->getOptions())->toBe([]);
});

it('works without an AsCommand attribute', function (): void {
    $command = new NoAttributeCommand();

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('no-attribute');
});

it('uses the default argument value when none is provided', function (): void {
    $result = (new CommandTester(new DemoCommand()))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('hello=World|false');
});

it('uses the provided argument and option values', function (): void {
    $result = (new CommandTester(new DemoCommand()))->run([
        'name' => 'Ada',
        '--greet' => true,
    ]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('hello=Ada|true');
});

it('returns success when __invoke returns nothing', function (): void {
    $result = (new CommandTester(new NullReturnCommand()))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('null-returned');
});

it('runs another command through the application', function (): void {
    $app = new Application('/');
    $console = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $console->addCommand(new CallerCommand());
    $console->addCommand(new TargetCommand());

    $result = (new CommandTester($console->find('demo:caller')))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('caller-run')
        ->and($result->getDisplay())->toContain('target-run:World');

    $app->flush();
});

it('reports a failure when the called command throws', function (): void {
    $app = new Application('/');
    $console = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
    $console->addCommand(new CallerCommand());
    $console->addCommand(new ThrowingTargetCommand());

    $result = (new CommandTester($console->find('demo:caller')))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Unable to execute command 'demo:target': boom");

    $app->flush();
});

it('reports a failure when the command has no application', function (): void {
    $result = (new CommandTester(new CallerCommand()))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('The application instance is not available.');
});

it('supports option suggested values as an array', function (): void {
    $command = new SuggestedArrayCommand();

    expect($command->getDefinition()->getOption('sug')->hasCompletion())->toBeTrue();
});

it('throws when an argument configuration has too few elements', function (): void {
    new BadArgumentCountCommand();
})->throws(InvalidArgumentException::class);

it('throws when an option configuration has too few elements', function (): void {
    new BadOptionCountCommand();
})->throws(InvalidArgumentException::class);

it('throws when an argument mode is not an integer', function (): void {
    new BadArgumentModeCommand();
})->throws(InvalidArgumentException::class);

it('throws when an argument description is not a string', function (): void {
    new BadArgumentDescriptionCommand();
})->throws(InvalidArgumentException::class);

it('throws when an option mode is not an integer', function (): void {
    new BadOptionModeCommand();
})->throws(InvalidArgumentException::class);

it('throws when an option description is not a string', function (): void {
    new BadOptionDescriptionCommand();
})->throws(InvalidArgumentException::class);

it('throws when an option shortcut is invalid', function (): void {
    new BadOptionShortcutCommand();
})->throws(InvalidArgumentException::class);

it('throws when suggested values are neither an array nor a closure', function (): void {
    new BadSuggestedValuesCommand();
})->throws(InvalidArgumentException::class);
