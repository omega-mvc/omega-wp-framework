<?php

declare(strict_types=1);

namespace Tests\Application;

use Omega\Application\ApplicationTheme;
use Omega\Application\Exceptions\HeaderNotFoundException;
use Tests\Application\Support\ApplicationFixture;
use Tests\Routing\Support\WPTheme;
use Tests\Routing\WordPressRuntime;

covers(ApplicationTheme::class, HeaderNotFoundException::class);

it('constructs with a valid base path', function (): void {
    $app = new ApplicationTheme('theme', ApplicationFixture::themeBasePath());

    expect($app->getId())->toBe('theme')
        ->and($app->getBasePath())->toBe(ApplicationFixture::themeBasePath());
});

it('returns the theme name', function (): void {
    $app = new ApplicationTheme('theme', ApplicationFixture::themeBasePath());

    expect($app->getName())->toBe('Omega Theme');
});

it('returns the theme version', function (): void {
    $app = new ApplicationTheme('theme', ApplicationFixture::themeBasePath());

    expect($app->getVersion())->toBe('1.0.0');
});

it('returns a theme header value read by wp_get_theme', function (): void {
    WordPressRuntime::$theme = new WPTheme(['Theme Name' => 'Omega Sample']);

    $app = new ApplicationTheme('theme', ApplicationFixture::themeBasePath());

    expect($app->getHeaderField('Theme Name'))->toBe('Omega Sample');
});

it('raises an exception for an empty theme header', function (): void {
    WordPressRuntime::$theme = new WPTheme(['Theme Name' => '']);

    $app = new ApplicationTheme('theme', ApplicationFixture::themeBasePath());

    $app->getHeaderField('Theme Name');
})->throws(HeaderNotFoundException::class);

it('keeps the header not found exception autoloadable and typed', function (): void {
    expect(class_exists(HeaderNotFoundException::class))->toBeTrue()
        ->and((new HeaderNotFoundException('sample'))->getMessage())->toBe('sample')
        ->and((new HeaderNotFoundException('Theme header "%s" not found.', 'Theme Name'))->getMessage())
        ->toBe('Theme header "Theme Name" not found.');
});
