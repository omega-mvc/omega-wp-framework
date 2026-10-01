<?php

declare(strict_types=1);

namespace Tests\Application;

use function function_exists;
use function Omega\Application\slash;
use function str_replace;

use const DIRECTORY_SEPARATOR;

covers('Omega\Application\slash');

it('declares the namespaced helper', function (): void {
    // Regression: the former `function_exists('slash')` guard checked the
    // *global* scope, so if a global `slash()` happened to exist the namespaced
    // `Omega\Application\slash` was silently never declared.
    expect(function_exists('Omega\Application\slash'))->toBeTrue();
});

it('normalizes string paths to the platform separator', function (): void {
    $input = '/var/www/html';

    expect(slash($input))->toBe(str_replace('/', DIRECTORY_SEPARATOR, $input));
});

it('returns a path without separators unchanged', function (): void {
    expect(slash('plain'))->toBe('plain');
});

it('returns an empty path unchanged', function (): void {
    expect(slash(''))->toBe('');
});

it('normalizes arrays of paths recursively', function (): void {
    $input = ['/var/www', ['/tmp', '/var/log']];

    $expected = [
        str_replace('/', DIRECTORY_SEPARATOR, '/var/www'),
        [
            str_replace('/', DIRECTORY_SEPARATOR, '/tmp'),
            str_replace('/', DIRECTORY_SEPARATOR, '/var/log'),
        ],
    ];

    expect(slash($input))->toBe($expected);
});
