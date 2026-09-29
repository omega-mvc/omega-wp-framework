<?php

declare(strict_types=1);

/*
 * Part of Omega - Tests\Console\Commands Package.
 *
 * @link      https://omegamvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright 2025-2026 The Omega MVC Framework
 * @license   https://opensource.org/license/mit The MIT License
 * @version   2.0.0
 */

namespace Tests\Console\Commands;

use Exception;
use Omega\Application\Application;
use Omega\Console\Commands\VendorPublishCommand;
use Omega\Container\AbstractServiceProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function file_get_contents;
use function file_put_contents;
use function mkdir;

covers(VendorPublishCommand::class);

function vendorableDir(string $prefix): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4));
    mkdir($path, 0777, true);

    return $path;
}

it('reports when there are no publishable resources', function (): void {
    $app = new Application(vendorableDir('omegavp'));

    AbstractServiceProvider::flushModule();

    $command = new VendorPublishCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('No publishable resources found.');

    AbstractServiceProvider::flushModule();
    $app->flush();
});

it('publishes a single file module', function (): void {
    $base = vendorableDir('omegavp');

    mkdir($base . '/vendor', 0777, true);

    $source = $base . '/vendor/config.php';
    $target = $base . '/app/config/config.php';

    file_put_contents($source, '<?php return [\'published\' => true];');
    AbstractServiceProvider::export([$source => $target], 'config');

    $app = new Application($base);

    $command = new VendorPublishCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--tag' => 'config']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Publishing resources...')
        ->and($result->getDisplay())->toContain('Done! 1 resource(s) have been successfully published.')
        ->and(file_get_contents($target))->toBe('<?php return [\'published\' => true];');

    AbstractServiceProvider::flushModule();
    $app->flush();
});

it('reports an error when the tag does not match any module', function (): void {
    $base = vendorableDir('omegavp');

    mkdir($base . '/vendor', 0777, true);

    $source = $base . '/vendor/config.php';
    $target = $base . '/app/config/config.php';

    file_put_contents($source, 'x');
    AbstractServiceProvider::export([$source => $target], 'config');

    $app = new Application($base);

    $command = new VendorPublishCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--tag' => 'assets']);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('No publishable resources found for tag: assets');

    AbstractServiceProvider::flushModule();
    $app->flush();
});

it('publishes every module when the tag is a wildcard', function (): void {
    $base = vendorableDir('omegavp');

    mkdir($base . '/vendor', 0777, true);

    $sourceA = $base . '/vendor/a.txt';
    $sourceB = $base . '/vendor/b.txt';
    $targetA = $base . '/app/a/a.txt';
    $targetB = $base . '/app/b/b.txt';

    file_put_contents($sourceA, 'a');
    file_put_contents($sourceB, 'b');
    AbstractServiceProvider::export([$sourceA => $targetA], 'assets');
    AbstractServiceProvider::export([$sourceB => $targetB], 'config');

    $app = new Application($base);

    $command = new VendorPublishCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Done! 2 resource(s) have been successfully published.')
        ->and(file_exists($targetA))->toBeTrue()
        ->and(file_exists($targetB))->toBeTrue();

    AbstractServiceProvider::flushModule();
    $app->flush();
});

it('publishes a directory module recursively', function (): void {
    $base = vendorableDir('omegavp');

    $sourceDir = $base . '/vendor/views';
    $targetDir = $base . '/resources/views';

    mkdir($sourceDir . '/nested', 0777, true);
    file_put_contents($sourceDir . '/welcome.view.php', 'welcome');
    file_put_contents($sourceDir . '/nested/page.view.php', 'page');
    AbstractServiceProvider::export([$sourceDir => $targetDir], 'views');

    $app = new Application($base);

    $command = new VendorPublishCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run(['--tag' => 'views']);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getDisplay())->toContain('Done! 1 resource(s) have been successfully published.')
        ->and(file_get_contents($targetDir . '/welcome.view.php'))->toBe('welcome')
        ->and(file_get_contents($targetDir . '/nested/page.view.php'))->toBe('page');

    AbstractServiceProvider::flushModule();
    $app->flush();
});

it('reports a failure when a module cannot be published', function (): void {
    $base = vendorableDir('omegavp');

    $missing = $base . '/vendor/missing.txt';
    $target = $base . '/app/missing.txt';

    AbstractServiceProvider::export([$missing => $target], 'assets');

    $app = new Application($base);

    $command = new VendorPublishCommand();
    $command->app = $app;

    $result = (new CommandTester($command))->run([]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain('Done! 0 resource(s) have been successfully published.')
        ->and(file_exists($target))->toBeFalse();

    AbstractServiceProvider::flushModule();
    $app->flush();
});

it('refuses to overwrite an existing file unless forced', function (): void {
    $base = vendorableDir('omegavp');

    mkdir($base . '/vendor', 0777, true);

    $source = $base . '/vendor/config.php';
    $target = $base . '/app/config/config.php';

    mkdir(dirname($target), 0777, true);
    file_put_contents($source, 'new');
    file_put_contents($target, 'old');
    AbstractServiceProvider::export([$source => $target], 'config');

    $app = new Application($base);

    $command = new VendorPublishCommand();
    $command->app = $app;

    $tester = new CommandTester($command);

    expect(static function () use ($tester): void {
        $tester->run(['--tag' => 'config']);
    })->toThrow(Exception::class, 'You do not have permission to overwrite the destination file.')
        ->and(file_get_contents($target))->toBe('old');

    $result = (new CommandTester($command))->run(['--tag' => 'config', '--force' => true]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and(file_get_contents($target))->toBe('new');

    AbstractServiceProvider::flushModule();
    $app->flush();
});