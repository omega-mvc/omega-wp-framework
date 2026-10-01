<?php

declare(strict_types=1);

namespace Tests\Application;

use Omega\Admin\AdminManager;
use Omega\Application\AbstractApplication;
use Omega\Application\Application;
use Omega\Application\ApplicationInterface;
use Omega\Config\ConfigRepository;
use Omega\Container\Container;
use Omega\Container\ContainerInterface;
use Omega\Container\Exceptions\ClassNotFoundException;
use Omega\Database\Database;
use Omega\Database\Migrations\Migrator;
use Omega\Routing\RouteLoader;
use Omega\Routing\RouterBuilder;
use Omega\Settings\SettingsRepository;
use Omega\View\View;
use ReflectionMethod;
use Tests\Application\Support\AbstractApplicationStub;
use Tests\Application\Support\ApplicationFixture;
use Tests\Application\Support\FakeProvider;
use Tests\Application\Support\PlainProvider;

covers(AbstractApplication::class);

it('registers the core bindings and the base services in cli mode', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath());

    expect($app->resolve(ContainerInterface::class))->toBe($app)
        ->and($app->resolve(Container::class))->toBe($app)
        ->and($app->resolve(ApplicationInterface::class))->toBe($app)
        ->and($app->resolve('config'))->toBeInstanceOf(ConfigRepository::class)
        ->and($app->resolve('settings'))->toBeInstanceOf(SettingsRepository::class)
        ->and($app->resolve('router'))->toBeInstanceOf(RouterBuilder::class)
        ->and($app->resolve(RouteLoader::class))->toBeInstanceOf(RouteLoader::class)
        ->and($app->resolve('database'))->toBeInstanceOf(Database::class)
        ->and($app->resolve('migrator'))->toBeInstanceOf(Migrator::class);
});

it('skips the view and admin services in cli mode', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath());

    $app->resolve('view');
})->throws(ClassNotFoundException::class);

it('registers the view and admin services outside cli mode', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath(), false);

    expect($app->resolve('view'))->toBeInstanceOf(View::class)
        ->and($app->resolve('admin.manager'))->toBeInstanceOf(AdminManager::class);
});

it('detects the current process sapi in the cli check', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    // ReflectionMethod::setAccessible() is a no-op since PHP 8.1 and is
    // deprecated since PHP 8.5, so invoking the protected method directly is
    // equivalent on every supported version.
    $method = new ReflectionMethod(AbstractApplication::class, 'isCli');

    expect($method->invoke($app))->toBeTrue();
});

it('registers the providers declared in the config file', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::pluginBasePath());

    expect(FakeProvider::$registerCalls)->toBe(1)
        ->and($app->resolve('fake.service'))->toBe('fake');
});

it('ignores a providers file without an array return', function (): void {
    new AbstractApplicationStub('sample', ApplicationFixture::nonArrayProvidersBasePath());

    expect(FakeProvider::$registerCalls)->toBe(0);
});

it('boots only the providers exposing a boot method on bootstrap', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath());

    $app->register(FakeProvider::class);
    $app->register(PlainProvider::class);

    $app->bootstrap();

    expect(FakeProvider::$bootCalls)->toBe(1);
});

it('instantiates and registers a string provider once', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath());

    $provider = $app->register(FakeProvider::class);

    expect($provider)->toBeInstanceOf(FakeProvider::class)
        ->and(FakeProvider::$registerCalls)->toBe(1)
        ->and($app->register(FakeProvider::class))->toBe($provider)
        ->and(FakeProvider::$registerCalls)->toBe(1);
});

it('stores and registers a provider instance once', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath());

    $instance = new FakeProvider($app);

    expect($app->register($instance))->toBe($instance)
        ->and(FakeProvider::$registerCalls)->toBe(1)
        ->and($app->register($instance))->toBe($instance)
        ->and(FakeProvider::$registerCalls)->toBe(1);
});

it('accepts a string provider without lifecycle methods', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath());

    expect($app->register(PlainProvider::class))->toBeInstanceOf(PlainProvider::class);
});

it('stores as-is a provider instance without lifecycle methods', function (): void {
    $app = new AbstractApplicationStub('sample', ApplicationFixture::themeBasePath());

    $instance = new PlainProvider();

    expect($app->register($instance))->toBe($instance);
});
