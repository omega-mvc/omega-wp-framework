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

use Omega\Console\Attribute\AsCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the AsCommand attribute value object.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AsCommand::class)]
final class AsCommandTest extends TestCase
{
    /**
     * Test the attribute exposes the command name.
     */
    public function testExposesTheCommandName(): void
    {
        $attribute = new AsCommand('demo:command');

        $this->assertSame('demo:command', $attribute->name);
    }

    /**
     * Test the attribute applies the default optional values.
     */
    public function testAppliesTheDefaultOptionalValues(): void
    {
        $attribute = new AsCommand('demo:command');

        $this->assertNull($attribute->description);
        $this->assertSame([], $attribute->arguments);
        $this->assertSame([], $attribute->options);
        $this->assertSame([], $attribute->aliases);
        $this->assertFalse($attribute->hidden);
    }

    /**
     * Test the attribute stores the full configuration.
     */
    public function testStoresTheFullConfiguration(): void
    {
        $attribute = new AsCommand(
            'demo:command',
            'A demonstration command.',
            ['name' => [2, 'The name']],
            ['greet' => ['g', 1, 'Greet']],
            ['demo:c'],
            true,
        );

        $this->assertSame('demo:command', $attribute->name);
        $this->assertSame('A demonstration command.', $attribute->description);
        $this->assertSame(['name' => [2, 'The name']], $attribute->arguments);
        $this->assertSame(['greet' => ['g', 1, 'Greet']], $attribute->options);
        $this->assertSame(['demo:c'], $attribute->aliases);
        $this->assertTrue($attribute->hidden);
    }
}
