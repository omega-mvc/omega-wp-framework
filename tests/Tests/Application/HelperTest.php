<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Application;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function Omega\Application\slash;
use function str_replace;

use const DIRECTORY_SEPARATOR;

/**
 * Tests the slash() application helper.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversFunction('Omega\Application\slash')]
final class HelperTest extends TestCase
{
    /**
     * Test the namespaced helper is always declared.
     *
     * Regression: the former `function_exists('slash')` guard checked the
     * *global* scope, so if a global `slash()` happened to exist the namespaced
     * `Omega\Application\slash` was silently never declared.
     */
    public function testHelperIsDeclared(): void
    {
        $this->assertTrue(function_exists('Omega\Application\slash'));
    }

    /**
     * Test string paths are normalized to the platform separator.
     */
    public function testStringPathIsNormalized(): void
    {
        $input = '/var/www/html';

        $this->assertSame(str_replace('/', DIRECTORY_SEPARATOR, $input), slash($input));
    }

    /**
     * Test a path without separators is returned unchanged.
     */
    public function testPathWithoutSeparatorsIsUnchanged(): void
    {
        $this->assertSame('plain', slash('plain'));
    }

    /**
     * Test an empty path is returned unchanged.
     */
    public function testEmptyPathIsUnchanged(): void
    {
        $this->assertSame('', slash(''));
    }

    /**
     * Test arrays are normalized recursively.
     */
    public function testArrayPathsAreNormalizedRecursively(): void
    {
        $input = ['/var/www', ['/tmp', '/var/log']];

        $expected = [
            str_replace('/', DIRECTORY_SEPARATOR, '/var/www'),
            [
                str_replace('/', DIRECTORY_SEPARATOR, '/tmp'),
                str_replace('/', DIRECTORY_SEPARATOR, '/var/log'),
            ],
        ];

        $this->assertSame($expected, slash($input));
    }
}
