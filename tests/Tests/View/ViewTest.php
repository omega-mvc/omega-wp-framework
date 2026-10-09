<?php

/**
 * Part of Omega - Tests View Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\View;

use Omega\Application\ApplicationInterface;
use Omega\View\Exception\ViewFileNotFoundException;
use Omega\View\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function ob_get_level;
use function Omega\Application\slash;

/**
 * Tests the View rendering class.
 *
 * @category  Tests
 * @package   View
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(View::class)]
#[CoversClass(ViewFileNotFoundException::class)]
final class ViewTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = slash(path: __DIR__ . '/../fixtures/app/theme');
    }

    /**
     * Test a view renders with extracted data.
     */
    public function testRendersViewWithExtractedData(): void
    {
        $this->assertSame('Hello, Omega!', $this->makeRenderer()->render('welcome', ['name' => 'Omega']));
    }

    /**
     * Test dot notation resolves nested view paths.
     */
    public function testResolvesNestedViewPaths(): void
    {
        $this->assertSame(
            'Nested greeting for John',
            $this->makeRenderer()->render('nested.hello', ['greeting' => 'John'])
        );
    }

    /**
     * Test the template scope is isolated from the locals of the renderer.
     *
     * Including the file directly from render() used to expose $view, $viewPath,
     * $data and $this to the template, which could then print them by mistake.
     */
    public function testIsolatesTheTemplateScopeFromTheRendererLocals(): void
    {
        $this->assertSame(
            'view=no viewPath=no data=no this=no locals=none name=Omega',
            $this->makeRenderer()->render('scope', ['name' => 'Omega'])
        );
    }

    /**
     * Test the `$e` helper escapes the data a template echoes.
     */
    public function testExposesAnEscapingHelperToTemplates(): void
    {
        $this->assertSame(
            "Hello, &lt;script&gt;alert(1)&lt;/script&gt;!\n",
            $this->makeRenderer()->render('page', ['name' => '<script>alert(1)</script>'])
        );
    }

    /**
     * Test a `layout` data key wraps the child view in a parent template.
     *
     * The parent receives the child output as `$content` while the original
     * data stays available to the layout itself.
     */
    public function testRendersTheChildViewWithinItsLayout(): void
    {
        $this->assertSame(
            "[layout:Omega &lt;b&gt;:Omega &amp; Co]Hello, Omega &amp; Co!\n[/layout]\n",
            $this->makeRenderer()->render('page', [
                'layout' => 'layouts.app',
                'title'  => 'Omega <b>',
                'name'   => 'Omega & Co',
            ])
        );
    }

    /**
     * Test a missing view file raises an exception.
     */
    public function testThrowsWhenViewFileDoesNotExist(): void
    {
        $this->expectException(ViewFileNotFoundException::class);
        $this->expectExceptionMessage('View file not found: `missing`');

        $this->makeRenderer()->render('missing');
    }

    /**
     * Test a template exception is rethrown and the output buffer is cleaned.
     */
    public function testRethrowsTemplateExceptionAndCleansOutputBuffer(): void
    {
        $levelBefore = ob_get_level();

        try {
            $this->makeRenderer()->render('broken');
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('View exploded.', $e->getMessage());
        }

        $this->assertSame($levelBefore, ob_get_level());
    }

    /**
     * Build a renderer backed by the theme fixture path.
     *
     * @return View View renderer instance
     */
    private function makeRenderer(): View
    {
        $app = $this->createStub(ApplicationInterface::class);
        $app->method('getBasePath')->willReturn($this->basePath);

        return new View($app);
    }
}
