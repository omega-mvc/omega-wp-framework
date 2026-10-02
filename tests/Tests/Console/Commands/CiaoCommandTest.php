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

namespace Tests\Console\Commands;

use Omega\Console\Commands\CiaoCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the CiaoCommand greeting command.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(CiaoCommand::class)]
final class CiaoCommandTest extends TestCase
{
    /**
     * Test the command prints a hello world message.
     */
    public function testPrintsAHelloWorldMessage(): void
    {
        $result = (new CommandTester(new CiaoCommand()))->run([]);

        $this->assertSame(Command::SUCCESS, $result->statusCode);
        $this->assertStringContainsString('Ciao Mondo!.', $result->getDisplay());
    }
}
