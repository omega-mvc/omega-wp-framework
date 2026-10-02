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

namespace Tests\Console\Attribute;

use Omega\Console\Attribute\Make;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Make attribute value object.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Make::class)]
final class MakeTest extends TestCase
{
    /**
     * Test the attribute stores the make configuration.
     */
    public function testStoresTheMakeConfiguration(): void
    {
        $attribute = new Make(
            'stub.php',
            'path.provider',
            '{{CLASS}}',
            'Provider',
            'app/Providers',
            'Created provider.',
            'Provider already exists.',
            ['class' => 'Foo'],
        );

        $this->assertSame('stub.php', $attribute->template);
        $this->assertSame('path.provider', $attribute->path);
        $this->assertSame('{{CLASS}}', $attribute->pattern);
        $this->assertSame('Provider', $attribute->suffix);
        $this->assertSame('app/Providers', $attribute->target);
        $this->assertSame('Created provider.', $attribute->info);
        $this->assertSame('Provider already exists.', $attribute->warning);
        $this->assertSame(['class' => 'Foo'], $attribute->vars);
    }

    /**
     * Test the template variables default to an empty array.
     */
    public function testDefaultsTheTemplateVariablesToAnEmptyArray(): void
    {
        $attribute = new Make(
            'stub.php',
            'path.provider',
            '{{CLASS}}',
            'Provider',
            'app/Providers',
            'Created provider.',
            'Provider already exists.',
        );

        $this->assertSame([], $attribute->vars);
    }
}
