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
use Omega\Console\CommandLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Tests\Console\Fixtures\DemoCommand;
use Tests\Console\Fixtures\PlainCommand;

/**
 * Tests the CommandLoader command factory.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(CommandLoader::class)]
final class CommandLoaderTest extends TestCase
{
    /**
     * Test an AbstractCommand instance is returned with the application injected.
     */
    public function testReturnsAnAbstractCommandInstanceWithTheApplicationInjected(): void
    {
        $app = new Application('omega', '/');
        $app->bindFactory(DemoCommand::class, static fn (): DemoCommand => new DemoCommand());
        $loader = new CommandLoader($app, ['demo:hello' => DemoCommand::class]);

        $command = $loader->get('demo:hello');

        $this->assertInstanceOf(DemoCommand::class, $command);

        $property = new ReflectionProperty(DemoCommand::class, 'app');

        $this->assertSame($app, $property->getValue($command));
    }

    /**
     * Test a plain Symfony command is returned without injecting the application.
     */
    public function testReturnsAPlainSymfonyCommandWithoutInjectingTheApplication(): void
    {
        $app = new Application('omega', '/');
        $app->bindFactory(PlainCommand::class, static fn (): PlainCommand => new PlainCommand());
        $loader = new CommandLoader($app, ['plain:run' => PlainCommand::class]);

        $command = $loader->get('plain:run');

        $this->assertInstanceOf(PlainCommand::class, $command);
    }

    /**
     * Test the loader reports whether a command name is defined.
     */
    public function testReportsWhetherACommandNameIsDefined(): void
    {
        $loader = new CommandLoader(new Application('omega', '/'), ['demo:hello' => DemoCommand::class]);

        $this->assertTrue($loader->has('demo:hello'));
        $this->assertFalse($loader->has('unknown:command'));
    }

    /**
     * Test the loader returns all defined command names.
     */
    public function testReturnsAllDefinedCommandNames(): void
    {
        $loader = new CommandLoader(new Application('omega', '/'), [
            'demo:hello' => DemoCommand::class,
            'plain:run' => PlainCommand::class,
        ]);

        $this->assertSame(['demo:hello', 'plain:run'], $loader->getNames());
    }

    /**
     * Test the loader throws when the command is not defined.
     */
    public function testThrowsWhenTheCommandIsNotDefined(): void
    {
        $loader = new CommandLoader(new Application('omega', '/'), []);

        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('Command "missing:command" is not defined.');

        $loader->get('missing:command');
    }
}
