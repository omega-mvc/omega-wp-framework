<?php

declare(strict_types=1);

namespace Tests\Console\Attribute;

use Omega\Console\Attribute\AsCommand;

covers(AsCommand::class);

it('exposes the command name', function (): void {
    $attribute = new AsCommand('demo:command');

    expect($attribute->name)->toBe('demo:command');
});

it('applies the default optional values', function (): void {
    $attribute = new AsCommand('demo:command');

    expect($attribute->description)->toBeNull()
        ->and($attribute->arguments)->toBe([])
        ->and($attribute->options)->toBe([])
        ->and($attribute->aliases)->toBe([])
        ->and($attribute->hidden)->toBeFalse();
});

it('stores the full configuration', function (): void {
    $attribute = new AsCommand(
        'demo:command',
        'A demonstration command.',
        ['name' => [2, 'The name']],
        ['greet' => ['g', 1, 'Greet']],
        ['demo:c'],
        true,
    );

    expect($attribute->name)->toBe('demo:command')
        ->and($attribute->description)->toBe('A demonstration command.')
        ->and($attribute->arguments)->toBe(['name' => [2, 'The name']])
        ->and($attribute->options)->toBe(['greet' => ['g', 1, 'Greet']])
        ->and($attribute->aliases)->toBe(['demo:c'])
        ->and($attribute->hidden)->toBeTrue();
});
