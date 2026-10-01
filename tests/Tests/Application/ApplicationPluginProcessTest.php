<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Tests\Application;

use Omega\Application\ApplicationPlugin;
use Omega\Application\Exceptions\HeaderNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Application\Support\FileDataParserDisabledStub;
use Tests\Routing\WordPressRuntime;

use function define;
use function Omega\Application\slash;

/**
 * Covers the parser bootstrap of ApplicationPlugin when a WordPress runtime root
 * is available.
 *
 * These two cases stay class-based on purpose. They are the only way to reach
 * the successful branch of ApplicationPlugin::loadFileDataParser(), because
 * reaching it requires the global ABSPATH constant, and a constant that has been
 * defined can never be undefined again: in the main test process it would leak
 * into the WordPressEnvironmentException case and make the suite order-dependent.
 *
 * Pest 5 exposes no equivalent of PHPUnit's #[RunInSeparateProcess] on its
 * functional it() calls, so the attribute cannot be applied here. Emulating the
 * isolation with a hand-rolled exec() subprocess is not an acceptable substitute
 * either: PHPUnit serializes the child process code coverage back into the parent
 * report (see PHPUnit\Framework\TestRunner\ChildProcessResultProcessor), while a
 * bare subprocess has no such channel and would silently drop these lines from the
 * coverage report.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
#[CoversClass(ApplicationPlugin::class)]
#[CoversClass(HeaderNotFoundException::class)]
final class ApplicationPluginProcessTest extends ApplicationTestCase
{
    #[RunInSeparateProcess]
    public function testGetHeaderFieldLoadsFileDataParserWhenWordPressIsPresent(): void
    {
        // Referencing the class before the constant is defined stops PHP from
        // constant-folding defined('ABSPATH') in this file. Without it the folded
        // value diverges from what the instrumented ApplicationPlugin evaluates,
        // and the branch coverage of loadFileDataParser() differs between processes.
        class_exists(FileDataParserDisabledStub::class);

        define('ABSPATH', slash(path: __DIR__ . '/../fixtures/app/plugin/wp/'));

        WordPressRuntime::$fileHeaders = ['Version' => '1.2.3'];

        $app = new FileDataParserDisabledStub('sample', $this->pluginBasePath());

        self::assertSame('1.2.3', $app->getHeaderField('Version'));
        self::assertTrue(defined($this->fixtureParserConstant()));
    }

    #[RunInSeparateProcess]
    public function testGetHeaderFieldLoadsFileDataParserAndThrowsOnEmptyHeader(): void
    {
        // Same constant-folding guard as the test above.
        class_exists(FileDataParserDisabledStub::class);

        define('ABSPATH', slash(path: __DIR__ . '/../fixtures/app/plugin/wp/'));

        WordPressRuntime::$fileHeaders = ['Version' => ''];

        $app = new FileDataParserDisabledStub('sample', $this->pluginBasePath());

        $this->expectException(HeaderNotFoundException::class);

        $app->getHeaderField('Version');
    }

    /**
     * Name of the constant the WordPress parser fixture defines.
     *
     * Returned through a method on purpose: a literal in the assertion would make
     * the check a tautology for static analysis.
     */
    private function fixtureParserConstant(): string
    {
        return 'OMEGA_FIXTURE_PLUGIN_PARSER_LOADED';
    }
}
