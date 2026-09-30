<?php

declare(strict_types=1);

namespace Tests\Console\Traits;

use Omega\Console\Traits\InteractWithFilesystemTrait;
use Tests\Console\Fixtures\FilesystemProbe;

covers(InteractWithFilesystemTrait::class);

it('returns an empty list when the directory does not exist', function (): void {
    $probe = new FilesystemProbe();

    expect($probe->find('/no/such/directory', '*'))->toBe([]);
});

it('matches files by a single pattern', function (): void {
    $probe = new FilesystemProbe();
    $files = $probe->find(__DIR__ . '/../Fixtures/Files', '*.txt');

    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('a.txt');
});

it('matches files by multiple patterns', function (): void {
    $probe = new FilesystemProbe();
    $files = $probe->find(__DIR__ . '/../Fixtures/Files', ['*.php', '*.log']);

    expect($files)->toHaveCount(2);
});

it('matches every file with the default pattern', function (): void {
    $probe = new FilesystemProbe();
    $files = $probe->find(__DIR__ . '/../Fixtures/Files');

    expect($files)->toHaveCount(3);
});

it('excludes files matching the given patterns', function (): void {
    $probe = new FilesystemProbe();
    $files = $probe->find(__DIR__ . '/../Fixtures/Files', '*', ['*.log']);

    expect($files)->toHaveCount(2)
        ->and($files)->not->toContain(
            __DIR__ . '/../Fixtures/Files/b.log'
        );
});
