<?php

declare(strict_types=1);

namespace Tests\Console\Attribute;

use Omega\Console\Attribute\Make;

covers(Make::class);

it('stores the make configuration', function (): void {
    $attribute = new Make(
        'stub.php',
        'path.provider',
        '{{CLASS}}',
        'Provider',
        'app/Providers',
        'Created provider.',
        'Provider already exists.',
        ['class' => 'Foo'],
    );

    expect($attribute->template)->toBe('stub.php')
        ->and($attribute->path)->toBe('path.provider')
        ->and($attribute->pattern)->toBe('{{CLASS}}')
        ->and($attribute->suffix)->toBe('Provider')
        ->and($attribute->target)->toBe('app/Providers')
        ->and($attribute->info)->toBe('Created provider.')
        ->and($attribute->warning)->toBe('Provider already exists.')
        ->and($attribute->vars)->toBe(['class' => 'Foo']);
});

it('defaults the template variables to an empty array', function (): void {
    $attribute = new Make(
        'stub.php',
        'path.provider',
        '{{CLASS}}',
        'Provider',
        'app/Providers',
        'Created provider.',
        'Provider already exists.',
    );

    expect($attribute->vars)->toBe([]);
});
