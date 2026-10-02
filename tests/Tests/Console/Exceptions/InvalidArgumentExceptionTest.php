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

namespace Tests\Console\Exceptions;

use LogicException;
use Omega\Console\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function get_parent_class;

/**
 * Tests the InvalidArgumentException console exception.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(InvalidArgumentException::class)]
final class InvalidArgumentExceptionTest extends TestCase
{
    /**
     * Test the exception is a logic exception.
     */
    public function testIsALogicException(): void
    {
        $exception = new InvalidArgumentException('Invalid configuration.');

        $this->assertSame(LogicException::class, get_parent_class($exception));
    }

    /**
     * Test the exception carries the configuration message.
     */
    public function testCarriesTheConfigurationMessage(): void
    {
        try {
            throw new InvalidArgumentException('Invalid configuration.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid configuration.', $exception->getMessage());
        }
    }
}
