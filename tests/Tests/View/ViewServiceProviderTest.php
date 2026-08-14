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

use Omega\Application\Application;
use Omega\View\View;
use Omega\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\FixturesPathTrait;

/**
 * Tests the ViewServiceProvider registration behavior.
 *
 * @category  Tests
 * @package   View
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ViewServiceProvider::class)]
final class ViewServiceProviderTest extends TestCase
{
    use FixturesPathTrait;

    /**
     * Test the provider binds the view service as a singleton.
     */
    public function testRegistersViewSingleton(): void
    {
        $app = new Application('theme', $this->setFixturePath('/fixtures/app/theme'));

        (new ViewServiceProvider($app))->register();

        $first = $app->resolve('view');
        $second = $app->resolve('view');

        $this->assertInstanceOf(View::class, $first);
        $this->assertSame($first, $second);
    }
}
