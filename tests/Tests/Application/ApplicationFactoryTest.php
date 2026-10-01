<?php

declare(strict_types=1);

namespace Tests\Application;

use Omega\Application\ApplicationFactory;
use Omega\Application\Exceptions\FileNotFoundException;
use Omega\Config\ConfigRepository;
use ReflectionProperty;
use RuntimeException;
use Tests\Application\Support\ApplicationFixture;
use Tests\Application\Support\FakeProvider;

covers(ApplicationFactory::class);

beforeEach(function (): void {
    $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
    $property->setValue(null, []);
});

afterEach(function (): void {
    $property = new ReflectionProperty(ApplicationFactory::class, 'apps');
    $property->setValue(null, []);
});

it('bootstraps a plugin application on createPlugin', function (): void {
    $app = ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());

    expect(ApplicationFactory::app())->toBe($app);
});

it('bootstraps a theme application on createTheme', function (): void {
    $app = ApplicationFactory::createTheme('theme', ApplicationFixture::themeBasePath());

    expect(ApplicationFactory::app())->toBe($app);
});

it('rejects a plugin without an entry file on createPlugin', function (): void {
    ApplicationFactory::createPlugin('sample', ApplicationFixture::missingPluginBasePath());
})->throws(FileNotFoundException::class);

it('loads and boots the providers declared in the providers file', function (): void {
    ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());

    expect(FakeProvider::$registerCalls)->toBe(1)
        ->and(FakeProvider::$bootCalls)->toBe(1);
});

it('returns the first registered application by default', function (): void {
    $plugin = ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());
    $theme = ApplicationFactory::createTheme('theme', ApplicationFixture::themeBasePath());

    expect(ApplicationFactory::app())->toBe($plugin)
        ->and(ApplicationFactory::app())->not->toBe($theme);
});

it('returns the requested application by id', function (): void {
    $plugin = ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());
    $theme = ApplicationFactory::createTheme('theme', ApplicationFixture::themeBasePath());

    expect(ApplicationFactory::app(null, 'theme'))->toBe($theme)
        ->and(ApplicationFactory::app(null, 'sample'))->toBe($plugin);
});

it('resolves a service from the first registered application', function (): void {
    ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());

    expect(ApplicationFactory::app('config'))->toBeInstanceOf(ConfigRepository::class)
        ->and(ApplicationFactory::app('fake.service'))->toBe('fake');
});

it('resolves a service from the requested application', function (): void {
    ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());
    ApplicationFactory::createTheme('theme', ApplicationFixture::themeBasePath());

    $sample = ApplicationFactory::app('config', 'sample');
    $theme = ApplicationFactory::app('config', 'theme');

    expect($sample)->toBeInstanceOf(ConfigRepository::class)
        ->and($theme)->toBeInstanceOf(ConfigRepository::class);

    // ApplicationFactory::app() is declared mixed, so assert() narrows the
    // type for static analysis; the expectations above carry the runtime check.
    assert($sample instanceof ConfigRepository);
    assert($theme instanceof ConfigRepository);

    expect($sample->string('app.environment', ''))->toBe('local')
        ->and($theme->string('app.environment', ''))->toBe('staging');
});

it('resolves the owning application from the execution stack', function (): void {
    ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());
    ApplicationFactory::createTheme('theme', ApplicationFixture::themeBasePath());
    ApplicationFactory::createPlugin('resolver', ApplicationFixture::resolverBasePath());

    $resolved = require ApplicationFixture::resolverBasePath() . '/resolve.php';

    expect($resolved)->toBeInstanceOf(ConfigRepository::class);

    // The resolver script has no declared return type, so assert() narrows it
    // for static analysis; the expectation above carries the runtime check.
    assert($resolved instanceof ConfigRepository);

    expect($resolved->string('app.environment', ''))->toBe('resolver');
});

it('resolves the application by its composer psr-4 namespace', function (): void {
    ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());
    ApplicationFactory::createPlugin('resolver', ApplicationFixture::resolverBasePath());

    expect(ApplicationFactory::app('Resolver\Contracts\Thing'))->toBe('resolver-thing');
});

it('falls back to the first application when no namespace matches', function (): void {
    ApplicationFactory::createPlugin('sample', ApplicationFixture::pluginBasePath());
    ApplicationFactory::createTheme('theme', ApplicationFixture::themeBasePath());
    ApplicationFactory::createPlugin('resolver', ApplicationFixture::resolverBasePath());

    expect(ApplicationFactory::app('fake.service'))->toBe('fake');
});

it('resolves a service from a non-first application by its psr-4 namespace', function (): void {
    ApplicationFactory::createTheme('theme', ApplicationFixture::themeBasePath());
    ApplicationFactory::createPlugin('resolver', ApplicationFixture::resolverBasePath());

    expect(ApplicationFactory::app('Resolver\Contracts\Thing'))->toBe('resolver-thing');
});

it('throws for an unregistered application id', function (): void {
    ApplicationFactory::app(null, 'unknown');
})->throws(RuntimeException::class, "No application registered for id 'unknown'.");

it('throws when no applications are registered', function (): void {
    ApplicationFactory::app('config');
})->throws(RuntimeException::class);
