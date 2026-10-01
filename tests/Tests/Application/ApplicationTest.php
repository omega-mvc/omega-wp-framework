<?php

declare(strict_types=1);

namespace Tests\Application;

use Omega\Application\Application;
use Omega\Application\ApplicationInterface;
use Omega\Application\Exceptions\MissingParameterException;
use Omega\Application\Exceptions\WordPressEnvironmentException;
use Omega\Config\ConfigRepository;
use Omega\Container\Container;
use Omega\Container\ContainerInterface;
use Omega\Container\Exceptions\ClassNotFoundException;
use Omega\Database\Database;
use Omega\Database\Migrations\Migrator;
use Omega\Routing\RouteLoader;
use Omega\Routing\RouterBuilder;
use Omega\Settings\SettingsRepository;
use Tests\Application\Support\ApplicationFixture;
use Tests\Application\Support\FakeProvider;
use Tests\Routing\Support\WPTheme;
use Tests\Routing\WordPressRuntime;

use function rtrim;

use const DIRECTORY_SEPARATOR;

covers(Application::class, MissingParameterException::class);

it('stores the id and normalizes the base path', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath() . '/');

    expect($app->getId())->toBe('sample')
        ->and($app->getBasePath())->toBe(rtrim(ApplicationFixture::themeBasePath(), DIRECTORY_SEPARATOR))
        ->and($app->getAppRoot())->toBe($app->getBasePath());
});

it('rejects an empty application id', function (): void {
    new Application('', ApplicationFixture::themeBasePath());
})->throws(MissingParameterException::class, 'The "id" parameter is required.');

it('rejects an empty base path', function (): void {
    new Application('sample', '');
})->throws(MissingParameterException::class, 'The "basePath" parameter is required.');

it('converts hyphens in the id to the underscore format', function (): void {
    $app = new Application('user-name', ApplicationFixture::themeBasePath());

    expect($app->getIdAsUnderscore())->toBe('user_name');
});

it('groups route files by type', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    $app->addRouteFile('/routes/api.php', 'api');
    $app->addRouteFile('/routes/admin.php', 'admin');
    $app->addRouteFile('/routes/api-extra.php', 'api');

    expect($app->getRestRouteFiles())->toBe(['/routes/api.php', '/routes/api-extra.php'])
        ->and($app->getAdminRouteFiles())->toBe(['/routes/admin.php']);
});

it('stores migration folders in insertion order', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    $app->addMigrationFolder('/database/migrations');
    $app->addMigrationFolder('/database/seeds');

    expect($app->getMigrationFolders())->toBe(['/database/migrations', '/database/seeds']);
});

it('resolves the configuration repository from the config directory', function (): void {
    $app = new Application('theme', ApplicationFixture::themeBasePath());

    expect($app->config()->string('app.name', ''))->toBe('Sample Theme');
});

it('reads the environment from the application configuration', function (): void {
    $app = new Application('theme', ApplicationFixture::themeBasePath());

    expect($app->getEnvironment())->toBe('staging');
});

it('falls back to the production environment when it is not configured', function (): void {
    $app = new Application('sample', ApplicationFixture::emptyBasePath());

    expect($app->getEnvironment())->toBe('production');
});

it('reads the debug mode from the application configuration', function (): void {
    $app = new Application('sample', ApplicationFixture::pluginBasePath());

    expect($app->isDebugMode())->toBeTrue();
});

it('defaults the debug mode to false', function (): void {
    $app = new Application('sample', ApplicationFixture::emptyBasePath());

    expect($app->isDebugMode())->toBeFalse();
});

it('builds the application cache path from the base path', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    expect($app->getApplicationCachePath())->toBe(ApplicationFixture::themeBasePath() . '/bootstrap/cache/');
});

it('points the application file to the theme stylesheet when the theme exists', function (): void {
    WordPressRuntime::$theme = new WPTheme([], true);

    $app = new Application('sample', ApplicationFixture::themeBasePath());

    expect($app->getAppFile())->toBe(ApplicationFixture::themeBasePath() . '/style.css');
});

it('points the application file to the plugin entry file when no theme matches', function (): void {
    WordPressRuntime::$theme = new WPTheme([], false);

    $app = new Application('sample', ApplicationFixture::themeBasePath());

    expect($app->getAppFile())->toBe(ApplicationFixture::themeBasePath() . '/sample.php');
});

it('resolves the settings repository', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    expect($app->settings())->toBe($app->resolve('settings'));
});

it('returns an empty string for any header field by default', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    expect($app->getHeaderField('Version'))->toBe('');
});

it('resolves the binding registered by a string provider', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    $provider = $app->register(FakeProvider::class);

    expect($provider)->toBeInstanceOf(FakeProvider::class)
        ->and(FakeProvider::$registerCalls)->toBe(1)
        ->and($app->resolve('fake.service'))->toBe('fake');
});

it('is idempotent when the same provider class is registered twice', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    $first = $app->register(FakeProvider::class);
    $second = $app->register(FakeProvider::class);

    expect($first)->toBe($second)
        ->and(FakeProvider::$registerCalls)->toBe(1);
});

it('returns a provider instance as-is', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    $provider = new FakeProvider($app);

    expect($app->register($provider))->toBe($provider);
});

it('invokes boot on every registered provider', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    $app->register(FakeProvider::class);
    $app->bootstrap();

    expect(FakeProvider::$bootCalls)->toBe(1);
});

it('resolves the core container bindings to the application instance', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    expect($app->resolve(ContainerInterface::class))->toBe($app)
        ->and($app->resolve(Container::class))->toBe($app)
        ->and($app->resolve(ApplicationInterface::class))->toBe($app);
});

it('registers the core framework services in the container', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    expect($app->resolve('config'))->toBeInstanceOf(ConfigRepository::class)
        ->and($app->resolve('settings'))->toBeInstanceOf(SettingsRepository::class)
        ->and($app->resolve('router'))->toBeInstanceOf(RouterBuilder::class)
        ->and($app->resolve(RouteLoader::class))->toBeInstanceOf(RouteLoader::class)
        ->and($app->resolve('database'))->toBeInstanceOf(Database::class)
        ->and($app->resolve('migrator'))->toBeInstanceOf(Migrator::class);
});

it('does not register the view provider in cli environments', function (): void {
    $app = new Application('sample', ApplicationFixture::themeBasePath());

    $app->resolve('view');
})->throws(ClassNotFoundException::class);

it('requires the WordPress runtime to resolve the database service', function (): void {
    global $wpdb;

    $savedWpdb = $wpdb;
    $wpdb = null;

    try {
        $app = new Application('sample', ApplicationFixture::themeBasePath());

        $app->resolve('database');
    } finally {
        $wpdb = $savedWpdb;
    }
})->throws(WordPressEnvironmentException::class);

it('keeps the missing parameter exception autoloadable and typed', function (): void {
    expect(class_exists(MissingParameterException::class))->toBeTrue()
        ->and((new MissingParameterException('sample'))->getMessage())->toBe('sample')
        ->and((new MissingParameterException('Parameter "%s" is required.', 'id'))->getMessage())
        ->toBe('Parameter "id" is required.');
});
