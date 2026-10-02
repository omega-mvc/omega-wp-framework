<?php

/**
 * Part of Omega - Tests Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Console;

use Omega\Application\Application;
use Omega\Console\ConsoleApplication;
use Omega\Console\ConsoleBranding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Console\Fixtures\ConsoleApplicationHarness;
use Tests\Console\Fixtures\ConsoleSupport;
use Tests\Console\Fixtures\DemoCommand;
use Tests\Console\Fixtures\PlainCommand;

use function copy;
use function dirname;
use function file_put_contents;
use function getenv;
use function mkdir;
use function putenv;
use function rmdir;
use function unlink;
use function var_export;

/**
 * Tests the ConsoleApplication command discovery and request handling.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ConsoleApplication::class)]
final class ConsoleApplicationTest extends TestCase
{
    /**
     * Test an empty map is returned when the cache file does not return an array.
     */
    public function testReturnsAnEmptyMapWhenTheCacheFileDoesNotReturnAnArray(): void
    {
        $base = ConsoleSupport::newConsoleBase();
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
        mkdir($cacheDir, 0777, true);
        file_put_contents($cacheDir . DIRECTORY_SEPARATOR . 'commands.php', '<?php return \'not-an-array\';');

        try {
            $app = new Application('omega', $base);
            $console = new ConsoleApplicationHarness($app);
            $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

            $console->exposeConfigureCommandLoader($branding);

            $this->assertFalse($branding->has('demo:hello'));
        } finally {
            @unlink($cacheDir . DIRECTORY_SEPARATOR . 'commands.php');
            @rmdir($cacheDir);
            ConsoleSupport::removeConsoleBase($base);
        }
    }

    /**
     * Test commands are discovered from the framework and application paths.
     */
    public function testDiscoversCommandsFromTheFrameworkAndApplicationPaths(): void
    {
        require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php';
        require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryAliasedCommand.php';
        require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryNoAttributeCommand.php';
        require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryNotCommand.php';

        $base = ConsoleSupport::newConsoleBase();
        $appCommands = $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Commands';
        mkdir($appCommands, 0777, true);

        $fixtures = [
            'DiscoveryCommand.php',
            'DiscoveryAliasedCommand.php',
            'DiscoveryNoAttributeCommand.php',
            'DiscoveryNotCommand.php',
        ];

        foreach ($fixtures as $fixture) {
            copy(__DIR__ . '/Fixtures/DiscoveredCommands/' . $fixture, $appCommands . DIRECTORY_SEPARATOR . $fixture);
        }

        try {
            $app = new Application('omega', $base);
            $console = new ConsoleApplicationHarness($app);

            $map = $console->discoverCommands();

            $this->assertSame('App\Console\Commands\DiscoveryCommand', $map['discover:one']);
            $this->assertSame('App\Console\Commands\DiscoveryAliasedCommand', $map['discover:two']);
            $this->assertSame('App\Console\Commands\DiscoveryAliasedCommand', $map['d2']);
            $this->assertSame('App\Console\Commands\DiscoveryAliasedCommand', $map['d3']);
            $this->assertArrayNotHasKey('discover:no-attribute', $map);
        } finally {
            foreach ($fixtures as $fixture) {
                @unlink($appCommands . DIRECTORY_SEPARATOR . $fixture);
            }
            @rmdir($appCommands);
            @rmdir(dirname($appCommands));
            ConsoleSupport::removeConsoleBase($base);
        }
    }

    /**
     * Test application paths that do not exist are skipped.
     */
    public function testSkipsApplicationsPathsThatDoNotExist(): void
    {
        $base = ConsoleSupport::newConsoleBase();

        try {
            $app = new Application('omega', $base);
            $console = new ConsoleApplicationHarness($app);

            $map = $console->discoverCommands();

            $this->assertArrayNotHasKey('discover:one', $map);
        } finally {
            ConsoleSupport::removeConsoleBase($base);
        }
    }

    /**
     * Test the command loader is attached from a cache file.
     */
    public function testAttachesTheCommandLoaderFromACacheFile(): void
    {
        $base = ConsoleSupport::newConsoleBase();
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
        mkdir($cacheDir, 0777, true);
        file_put_contents(
            $cacheDir . DIRECTORY_SEPARATOR . 'commands.php',
            '<?php return ' . var_export(['demo:hello' => DemoCommand::class], true) . ';'
        );

        try {
            $app = new Application('omega', $base);
            $console = new ConsoleApplicationHarness($app);
            $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

            $console->exposeConfigureCommandLoader($branding);

            $this->assertTrue($branding->has('demo:hello'));
        } finally {
            @unlink($cacheDir . DIRECTORY_SEPARATOR . 'commands.php');
            @rmdir($cacheDir);
            ConsoleSupport::removeConsoleBase($base);
        }
    }

    /**
     * Test the command loader is attached from discovered commands.
     */
    public function testAttachesTheCommandLoaderFromDiscoveredCommands(): void
    {
        require_once __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php';

        $base = ConsoleSupport::newConsoleBase();
        $appCommands = $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Commands';
        mkdir($appCommands, 0777, true);
        copy(
            __DIR__ . '/Fixtures/DiscoveredCommands/DiscoveryCommand.php',
            $appCommands . DIRECTORY_SEPARATOR . 'DiscoveryCommand.php',
        );

        try {
            $app = new Application('omega', $base);
            $console = new ConsoleApplicationHarness($app);
            $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');

            $console->exposeConfigureCommandLoader($branding);

            $this->assertTrue($branding->has('discover:one'));
        } finally {
            @unlink($appCommands . DIRECTORY_SEPARATOR . 'DiscoveryCommand.php');
            @rmdir($appCommands);
            @rmdir(dirname($appCommands));
            ConsoleSupport::removeConsoleBase($base);
        }
    }

    /**
     * Test console requests are executed in a subprocess.
     */
    public function testExecutesConsoleRequestsInASubprocess(): void
    {
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $base = ConsoleSupport::newConsoleBase();

        $scenarios = [
            [
                'input' => null,
                'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
                'shell' => '/bin/bash',
                'tokens' => ['pest', '--version'],
            ],
            [
                'input' => ['pest', '--version'],
                'output' => 'null',
                'shell' => '',
                'tokens' => [],
            ],
            [
                'input' => 'array-input',
                'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
                'shell' => '/usr/bin/nologin',
                'tokens' => ['pest', '--version'],
            ],
            [
                'input' => null,
                'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
                'shell' => '/usr/bin/zsh',
                'tokens' => ['pest', '--version'],
            ],
            [
                'input' => null,
                'output' => 'new \Symfony\Component\Console\Output\BufferedOutput()',
                'shell' => '/usr/bin/fish',
                'tokens' => ['pest', '--version'],
            ],
        ];

        foreach ($scenarios as $scenario) {
            $this->assertSame(0, ConsoleSupport::runHandleInSubprocess(
                $autoload,
                $base,
                $scenario['tokens'],
                $scenario['input'],
                $scenario['output'],
                $scenario['shell'],
            ));
        }
    }

    /**
     * Test a null input is handled by reading the process argv.
     */
    public function testHandlesANullInputReadingTheProcessArgv(): void
    {
        $previous = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['pest', '--version'];

        try {
            $app = new Application('omega', '/');
            $console = new ConsoleApplication($app);
            $output = new BufferedOutput();

            $this->assertSame(0, $console->handle(null, $output));
        } finally {
            if ($previous === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $previous;
            }
        }
    }

    /**
     * Test an array input is handled as a version request.
     */
    public function testHandlesAnArrayInputAsAVersionRequest(): void
    {
        $app = new Application('omega', '/');
        $console = new ConsoleApplication($app);
        $output = new BufferedOutput();

        $exit = $console->handle(['pest', '--version'], $output);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('omega Framework:', $output->fetch());
    }

    /**
     * Test a discovered command is run through handle.
     */
    public function testRunsADiscoveredCommandThroughHandle(): void
    {
        $app = new Application('omega', '/');
        $console = new ConsoleApplication($app);
        $output = new BufferedOutput();

        $exit = $console->handle(['pest', 'ciao'], $output);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Ciao Mondo!.', $output->fetch());
    }

    /**
     * Test a pre-built ArrayInput is passed through.
     */
    public function testPassesThroughAPreBuiltArrayInput(): void
    {
        $app = new Application('omega', '/');
        $console = new ConsoleApplication($app);
        $output = new BufferedOutput();

        $this->assertSame(0, $console->handle(new ArrayInput(['--version' => true]), $output));
    }

    /**
     * Test a ConsoleOutput is created when none is provided.
     */
    public function testCreatesAConsoleOutputWhenNoneIsProvided(): void
    {
        $app = new Application('omega', '/');
        $console = new ConsoleApplication($app);

        $this->assertSame(0, $console->handle(['pest', '--version']));
    }

    /**
     * Test SHELL is forced and supported shells are honoured.
     */
    public function testForcesShellAndHonoursSupportedShells(): void
    {
        $app = new Application('omega', '/');
        $console = new ConsoleApplication($app);
        $previous = getenv('SHELL');

        foreach (['/bin/bash', '/usr/bin/zsh', '/usr/bin/fish', '', '/usr/bin/dash'] as $shell) {
            try {
                putenv('SHELL=' . $shell);

                $this->assertSame(0, $console->handle(['pest', '--version'], new BufferedOutput()));
            } finally {
                if ($previous === false) {
                    putenv('SHELL');
                } else {
                    putenv('SHELL=' . $previous);
                }
            }
        }
    }

    /**
     * Test shell and input/output combinations are swept through handle.
     */
    public function testSweepsShellAndInputOutputCombinationsThroughHandle(): void
    {
        $previousArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['pest', '--version'];

        $shells = [
            '',
            'bash',
            'zsh',
            'fish',
            'dash',
            'bash zsh',
            'zsh fish',
            '/bin/bash',
            '/usr/bin/zsh',
            '/usr/bin/fish',
            'bash bash',
            'zsh fish bash',
            'x',
        ];
        $inputs = [
            null,
            ['pest', '--version'],
            new ArrayInput(['--version' => true]),
        ];
        $outputs = [
            new BufferedOutput(),
        ];

        try {
            foreach ($shells as $shell) {
                $previousShell = getenv('SHELL');

                try {
                    putenv('SHELL=' . $shell);

                    foreach ($inputs as $input) {
                        foreach ($outputs as $output) {
                            $app = new Application('omega', '/');
                            $console = new ConsoleApplication($app);

                            $this->assertSame(0, $console->handle($input, $output));
                        }
                    }
                } finally {
                    if ($previousShell === false) {
                        putenv('SHELL');
                    } else {
                        putenv('SHELL=' . $previousShell);
                    }
                }
            }
        } finally {
            if ($previousArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $previousArgv;
            }
        }
    }

    /**
     * Test an empty map is returned when the application command directory is empty.
     */
    public function testReturnsAnEmptyMapWhenTheApplicationCommandDirectoryIsEmpty(): void
    {
        $base = ConsoleSupport::newConsoleBase();
        $appCommands = $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Commands';
        mkdir($appCommands, 0777, true);

        try {
            $app = new Application('omega', $base);
            $console = new ConsoleApplicationHarness($app);

            $map = $console->discoverCommands();

            $this->assertArrayNotHasKey('discover:one', $map);
        } finally {
            @rmdir($appCommands);
            @rmdir(dirname($appCommands));
            ConsoleSupport::removeConsoleBase($base);
        }
    }

    /**
     * Test discovered files whose class is not autoloadable are ignored.
     */
    public function testIgnoresDiscoveredFilesWhoseClassIsNotAutoloadable(): void
    {
        $base = ConsoleSupport::newConsoleBase();
        $appCommands = $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Commands';
        mkdir($appCommands, 0777, true);
        file_put_contents(
            $appCommands . DIRECTORY_SEPARATOR . 'GhostCommand.php',
            '<?php namespace App\Console\Commands; class GhostCommand {}'
        );

        try {
            $app = new Application('omega', $base);
            $console = new ConsoleApplicationHarness($app);

            $map = $console->discoverCommands();

            $this->assertArrayNotHasKey('ghost:run', $map);
        } finally {
            @unlink($appCommands . DIRECTORY_SEPARATOR . 'GhostCommand.php');
            @rmdir($appCommands);
            @rmdir(dirname($appCommands));
            ConsoleSupport::removeConsoleBase($base);
        }
    }
}
