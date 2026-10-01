<?php

declare(strict_types=1);

namespace Tests\Environment;

use Tests\Environment\Support\EnvironmentFixture;

use function function_exists;
use function putenv;

use function Omega\Environment\env;

covers('Omega\Environment\env');

beforeEach(function (): void {
    EnvironmentFixture::reset();
});

it('declares the namespaced helper', function (): void {
    // Regression: the former `function_exists('env')` guard checked the
    // *global* scope, so if a global `env()` happened to exist the namespaced
    // `Omega\Environment\env` was silently never declared.
    expect(function_exists('Omega\Environment\env'))->toBeTrue();
});

it('returns loaded value', function (): void {
    EnvironmentFixture::setValues(['APP_NAME' => 'Omega']);

    expect(env('APP_NAME'))->toBe('Omega');
});

it('gives the loaded value precedence over the default', function (): void {
    EnvironmentFixture::setValues(['APP_NAME' => 'Omega']);

    expect(env('APP_NAME', 'default'))->toBe('Omega');
});

it('returns default when key not found', function (): void {
    EnvironmentFixture::setValues(['APP_NAME' => 'Omega']);

    expect(env('NON_EXISTING_KEY', 'fallback'))->toBe('fallback');
});

it('returns null when key not found without default', function (): void {
    EnvironmentFixture::setValues([]);

    expect(env('NON_EXISTING_KEY'))->toBeNull();
});

it('casts loaded values', function (): void {
    EnvironmentFixture::setValues([
        'APP_DEBUG'   => 'true',
        'APP_TIMEOUT' => '42',
        'APP_EMPTY'   => 'empty',
        'APP_NULL'    => 'null',
    ]);

    expect(env('APP_DEBUG'))->toBeTrue()
        ->and(env('APP_TIMEOUT'))->toBe(42)
        ->and(env('APP_EMPTY'))->toBe('')
        ->and(env('APP_NULL'))->toBeNull();
});

it('falls back to system environment', function (): void {
    putenv('OMEGA_HELPER_TEST=hello');

    try {
        expect(env('OMEGA_HELPER_TEST'))->toBe('hello');
    } finally {
        putenv('OMEGA_HELPER_TEST');
    }
});
