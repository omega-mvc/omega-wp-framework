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
use Omega\Console\ConsoleBranding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * Tests the ConsoleBranding header rendering.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ConsoleBranding::class)]
final class ConsoleBrandingTest extends TestCase
{
    /**
     * Test the header is skipped for silent commands.
     */
    public function testSkipsTheHeaderForSilentCommands(): void
    {
        $app = new Application('omega', '/');
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $output = new BufferedOutput();

        $exit = $branding->doRun(new ArrayInput(['--version']), $output);

        $this->assertSame(0, $exit);
        $this->assertStringNotContainsString('Environment:', $output->fetch());
    }

    /**
     * Test the branded header is rendered for non-silent commands.
     */
    public function testRendersTheBrandedHeaderForNonSilentCommands(): void
    {
        $app = new Application('omega', '/');
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
        $output = new BufferedOutput();

        $exit = $branding->doRun(new ArrayInput(['greet']), $output);
        $display = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('____', $display);
        $this->assertStringContainsString('Environment:', $display);
        $this->assertStringContainsString('Debug: OFF', $display);
        $this->assertStringContainsString('Command Cache: NO', $display);
    }

    /**
     * Test the debug status is rendered when debug mode is enabled.
     */
    public function testRendersTheDebugStatusWhenDebugModeIsEnabled(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omega-branding-' . bin2hex(random_bytes(4));
        $configDir = $base . DIRECTORY_SEPARATOR . 'config';
        $configFile = $configDir . DIRECTORY_SEPARATOR . 'app.php';
        mkdir($configDir, 0777, true);
        file_put_contents($configFile, '<?php return [\'debug\' => true];');

        try {
            $app = new Application('omega', $base);
            $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
            $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
            $output = new BufferedOutput();

            $branding->doRun(new ArrayInput(['greet']), $output);

            $this->assertStringContainsString('Debug: ON', $output->fetch());
        } finally {
            @unlink($configFile);
            @rmdir($configDir);
            @rmdir($base);
        }
    }

    /**
     * Test the command cache status is rendered when the cache file exists.
     */
    public function testRendersTheCommandCacheStatusWhenTheCacheFileExists(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omega-branding-' . bin2hex(random_bytes(4));
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'commands.php';
        mkdir($cacheDir, 0777, true);
        file_put_contents($cacheFile, '<?php return [];');

        try {
            $app = new Application('omega', $base);
            $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
            $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
            $output = new BufferedOutput();

            $branding->doRun(new ArrayInput(['greet']), $output);

            $this->assertStringContainsString('Command Cache: YES', $output->fetch());
        } finally {
            @unlink($cacheFile);
            @rmdir($cacheDir);
            @rmdir(dirname($cacheDir));
            @rmdir($base);
        }
    }

    /**
     * Test both the debug and command cache status are rendered as enabled.
     */
    public function testRendersBothTheDebugAndCommandCacheStatusAsEnabled(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omega-branding-' . bin2hex(random_bytes(4));
        $configDir = $base . DIRECTORY_SEPARATOR . 'config';
        $configFile = $configDir . DIRECTORY_SEPARATOR . 'app.php';
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'commands.php';
        mkdir($configDir, 0777, true);
        mkdir($cacheDir, 0777, true);
        file_put_contents($configFile, '<?php return [\'debug\' => true];');
        file_put_contents($cacheFile, '<?php return [];');

        try {
            $app = new Application('omega', $base);
            $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
            $branding->addCommand((new Command('greet'))->setCode(static fn (): int => 0));
            $output = new BufferedOutput();

            $branding->doRun(new ArrayInput(['greet']), $output);

            $display = $output->fetch();

            $this->assertStringContainsString('Debug: ON', $display);
            $this->assertStringContainsString('Command Cache: YES', $display);
        } finally {
            @unlink($cacheFile);
            @unlink($configFile);
            @rmdir($cacheDir);
            @rmdir($configDir);
            @rmdir(dirname($cacheDir));
            @rmdir($base);
        }
    }

    /**
     * Test silent commands are detected by their options.
     */
    public function testDetectsSilentCommandsByTheirOptions(): void
    {
        $app = new Application('omega', '/');
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $isSilent = new ReflectionMethod(ConsoleBranding::class, 'isSilentCommand');

        foreach (['--version', '-V', '--quiet', '-q'] as $token) {
            $this->assertTrue($isSilent->invoke($branding, new ArrayInput([$token])));
        }
    }

    /**
     * Test silent commands are detected by their first argument.
     */
    public function testDetectsSilentCommandsByTheirFirstArgument(): void
    {
        $app = new Application('omega', '/');
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $isSilent = new ReflectionMethod(ConsoleBranding::class, 'isSilentCommand');

        foreach (['list', 'help', 'completion'] as $token) {
            $this->assertTrue($isSilent->invoke($branding, new ArrayInput([$token])));
        }

        $this->assertFalse($isSilent->invoke($branding, new ArrayInput(['serve'])));
        $this->assertFalse($isSilent->invoke($branding, new ArrayInput([])));
    }

    /**
     * Test byte counts are formatted in human readable units.
     */
    public function testFormatsByteCountsInHumanReadableUnits(): void
    {
        $app = new Application('omega', '/');
        $branding = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $format = new ReflectionMethod(ConsoleBranding::class, 'formatBytes');

        $this->assertSame('0 B', $format->invoke($branding, 0));
        $this->assertSame('500 B', $format->invoke($branding, 500));
        $this->assertSame('1 KB', $format->invoke($branding, 1024));
        $this->assertSame('1.5 KB', $format->invoke($branding, 1536));
        $this->assertSame('1 MB', $format->invoke($branding, 1024 * 1024));
        $this->assertSame('1 GB', $format->invoke($branding, 1024 ** 3));
        $this->assertSame('2.5 GB', $format->invoke($branding, (int) (2.5 * 1024 ** 3)));
        $this->assertSame('1024 GB', $format->invoke($branding, 1024 ** 4));
    }
}
