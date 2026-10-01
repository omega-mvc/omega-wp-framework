<?php

declare(strict_types=1);

namespace Tests\Environment;

use Omega\Environment\Env;
use Tests\Environment\Support\EnvironmentFixture;

use function putenv;

covers(Env::class);

beforeEach(function (): void {
    EnvironmentFixture::reset();
});

it('can create immutable', function (): void {
    Env::load(EnvironmentFixture::fixturePath(), '.env.test');

    expect(Env::get('APP_NAME'))->toBe('Omega');
});

it('preserves values when loaded twice', function (): void {
    Env::load(EnvironmentFixture::fixturePath(), '.env.test');
    Env::load(EnvironmentFixture::fixturePath(), '.env.test');

    expect(Env::get('APP_NAME'))->toBe('Omega')
        ->and(Env::get('APP_DEBUG'))->toBeTrue()
        ->and(Env::get('APP_TIMEOUT'))->toBe(42)
        ->and(Env::get('APP_EMPTY'))->toBe('')
        ->and(Env::get('APP_NULL'))->toBeNull();
});

it('returns default value', function (): void {
    expect(Env::get('NON_EXISTING_KEY', 'default_value'))->toBe('default_value');
});

it('converts string representations', function (string $key, mixed $rawValue, mixed $expected): void {
    EnvironmentFixture::setValues([$key => $rawValue]);

    expect(Env::get($key))->toBe($expected);
})->with([
    'boolean true'             => ['BOOL_TRUE', 'true', true],
    'boolean false'            => ['BOOL_FALSE', 'false', false],
    'null keyword'             => ['NULL_VAL', 'null', null],
    'empty keyword'            => ['EMPTY_VAL', 'empty', ''],
    'integer string'           => ['NUMERIC_INT', '42', 42],
    'float string'             => ['NUMERIC_FLOAT', '3.14', 3.14],
    'plain string'             => ['NORMAL_STRING', 'Omega', 'Omega'],
    'alpha string'             => ['STRING_ALPHA', 'alpha', 'alpha'],
    'zero string'              => ['STRING_ZERO', '0', 0],
    'float with trailing zero' => ['STRING_FLOAT_STRANGE', '10.50', 10.5],
    'blank string'             => ['STRING_EMPTY_SPACE', ' ', ' '],
]);

it('returns non string values as is', function (): void {
    EnvironmentFixture::setValues([
        'ARRAY_VAL' => [1, 2, 3],
        'INT_VAL'   => 100,
        'BOOL_VAL'  => false,
        'NULL_VAL'  => null,
    ]);

    expect(Env::get('ARRAY_VAL'))->toBe([1, 2, 3])
        ->and(Env::get('INT_VAL'))->toBe(100)
        ->and(Env::get('BOOL_VAL'))->toBeFalse()
        ->and(Env::get('NULL_VAL'))->toBeNull();
});

it('falls back to getenv', function (): void {
    putenv('SYSTEM_VAR=hello');

    try {
        expect(Env::get('SYSTEM_VAR'))->toBe('hello');
    } finally {
        putenv('SYSTEM_VAR');
    }
});
