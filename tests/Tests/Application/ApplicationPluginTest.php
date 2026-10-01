<?php

declare(strict_types=1);

namespace Tests\Application;

use Omega\Application\ApplicationPlugin;
use Omega\Application\Exceptions\FileNotFoundException;
use Omega\Application\Exceptions\HeaderNotFoundException;
use Omega\Application\Exceptions\WordPressEnvironmentException;
use Tests\Application\Support\ApplicationFixture;
use Tests\Application\Support\FileDataParserDisabledStub;
use Tests\Routing\WordPressRuntime;

covers(
    ApplicationPlugin::class,
    FileNotFoundException::class,
    HeaderNotFoundException::class,
    WordPressEnvironmentException::class,
);

it('constructs with a valid plugin structure', function (): void {
    $app = new ApplicationPlugin('sample', ApplicationFixture::pluginBasePath());

    expect($app->getId())->toBe('sample')
        ->and($app->getBasePath())->toBe(ApplicationFixture::pluginBasePath());
});

it('rejects a missing plugin entry file', function (): void {
    new ApplicationPlugin('sample', ApplicationFixture::missingPluginBasePath());
})->throws(FileNotFoundException::class, 'sample');

it('returns the plugin name', function (): void {
    $app = new ApplicationPlugin('sample', ApplicationFixture::pluginBasePath());

    expect($app->getName())->toBe('Omega Plugin');
});

it('returns the plugin version', function (): void {
    $app = new ApplicationPlugin('sample', ApplicationFixture::pluginBasePath());

    expect($app->getVersion())->toBe('1.0.0');
});

it('returns a plugin header value read by get_file_data', function (): void {
    WordPressRuntime::$fileHeaders = ['Version' => '1.2.3'];

    $app = new ApplicationPlugin('sample', ApplicationFixture::pluginBasePath());

    expect($app->getHeaderField('Version'))->toBe('1.2.3');
});

it('raises an exception for an empty plugin header', function (): void {
    WordPressRuntime::$fileHeaders = ['Version' => ''];

    $app = new ApplicationPlugin('sample', ApplicationFixture::pluginBasePath());

    $app->getHeaderField('Version');
})->throws(HeaderNotFoundException::class);

it('throws when the WordPress parser is missing and no runtime root is available', function (): void {
    $app = new FileDataParserDisabledStub('sample', ApplicationFixture::pluginBasePath());

    $app->getHeaderField('Version');
})->throws(WordPressEnvironmentException::class, 'WordPress environment is not available.');

it('keeps the file not found exception autoloadable and typed', function (): void {
    expect(class_exists(FileNotFoundException::class))->toBeTrue()
        ->and((new FileNotFoundException('sample'))->getMessage())->toBe('sample')
        ->and((new FileNotFoundException('The file "%s" was not found.', 'sample.php'))->getMessage())
        ->toBe('The file "sample.php" was not found.');
});

it('keeps the header not found exception autoloadable and typed', function (): void {
    expect(class_exists(HeaderNotFoundException::class))->toBeTrue()
        ->and((new HeaderNotFoundException('sample'))->getMessage())->toBe('sample')
        ->and((new HeaderNotFoundException('Plugin header "%s" not found.', 'Version'))->getMessage())
        ->toBe('Plugin header "Version" not found.');
});

it('keeps the WordPress environment exception autoloadable and typed', function (): void {
    expect(class_exists(WordPressEnvironmentException::class))->toBeTrue()
        ->and((new WordPressEnvironmentException('sample'))->getMessage())->toBe('sample')
        ->and((new WordPressEnvironmentException('WordPress environment "%s" is not available.', 'test'))
            ->getMessage())
        ->toBe('WordPress environment "test" is not available.');
});
