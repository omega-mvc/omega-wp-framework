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
use Tests\FixturesPathTrait;

use function ob_get_level;

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
    use FixturesPathTrait;

    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = $this->setFixturePath('/fixtures/app/theme');
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
