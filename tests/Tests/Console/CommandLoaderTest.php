<?php

declare(strict_types=1);

namespace Tests\Console;

use Omega\Application\Application;
use Omega\Console\CommandLoader;
use ReflectionProperty;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Tests\Console\Fixtures\DemoCommand;
use Tests\Console\Fixtures\PlainCommand;

covers(CommandLoader::class);

it('returns an AbstractCommand instance with the application injected', function (): void {
    $app = new Application('/');
    $app->set(DemoCommand::class, static fn (): DemoCommand => new DemoCommand());
    $loader = new CommandLoader($app, ['demo:hello' => DemoCommand::class]);

    $command = $loader->get('demo:hello');

    expect($command)->toBeInstanceOf(DemoCommand::class);

    $property = new ReflectionProperty(DemoCommand::class, 'app');

    expect($property->getValue($command))->toBe($app);

    $app->flush();
});

it('returns a plain Symfony command without injecting the application', function (): void {
    $app = new Application('/');
    $app->set(PlainCommand::class, static fn (): PlainCommand => new PlainCommand());
    $loader = new CommandLoader($app, ['plain:run' => PlainCommand::class]);

    $command = $loader->get('plain:run');

    expect($command)->toBeInstanceOf(PlainCommand::class);

    $app->flush();
});

it('reports whether a command name is defined', function (): void {
    $loader = new CommandLoader(new Application('/'), ['demo:hello' => DemoCommand::class]);

    expect($loader->has('demo:hello'))->toBeTrue()
        ->and($loader->has('unknown:command'))->toBeFalse();
});

it('returns all defined command names', function (): void {
    $loader = new CommandLoader(new Application('/'), [
        'demo:hello' => DemoCommand::class,
        'plain:run' => PlainCommand::class,
    ]);

    expect($loader->getNames())->toBe(['demo:hello', 'plain:run']);
});

it('throws when the command is not defined', function (): void {
    $loader = new CommandLoader(new Application('/'), []);

    $loader->get('missing:command');
})->throws(CommandNotFoundException::class, 'Command "missing:command" is not defined.');
