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
use Omega\Console\AbstractCommand;
use Omega\Console\ConsoleBranding;
use Omega\Console\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Console\Fixtures\BadArgumentCountCommand;
use Tests\Console\Fixtures\BadArgumentDescriptionCommand;
use Tests\Console\Fixtures\BadArgumentModeCommand;
use Tests\Console\Fixtures\BadArgumentNotArrayCommand;
use Tests\Console\Fixtures\BadArgumentTooManyCommand;
use Tests\Console\Fixtures\BadOptionCountCommand;
use Tests\Console\Fixtures\BadOptionDescriptionCommand;
use Tests\Console\Fixtures\BadOptionModeCommand;
use Tests\Console\Fixtures\BadOptionNotArrayCommand;
use Tests\Console\Fixtures\BadOptionShortcutCommand;
use Tests\Console\Fixtures\BadOptionTooManyCommand;
use Tests\Console\Fixtures\BadSuggestedValuesCommand;
use Tests\Console\Fixtures\CallerCommand;
use Tests\Console\Fixtures\DemoCommand;
use Tests\Console\Fixtures\MinimalCommand;
use Tests\Console\Fixtures\NoAttributeCommand;
use Tests\Console\Fixtures\NullReturnCommand;
use Tests\Console\Fixtures\ShortcutOptionsCommand;
use Tests\Console\Fixtures\SuggestedArrayCommand;
use Tests\Console\Fixtures\TargetCommand;
use Tests\Console\Fixtures\ThrowingTargetCommand;

/**
 * Tests the AbstractCommand attribute driven configuration.
 *
 * @category  Tests
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractCommand::class)]
final class AbstractCommandTest extends TestCase
{
    /**
     * Test the command is configured from the AsCommand attribute.
     */
    public function testConfiguresTheCommandFromTheAsCommandAttribute(): void
    {
        $command = new DemoCommand();

        $this->assertSame('demo:hello', $command->getName());
        $this->assertSame('Demonstrates a command with arguments and options.', $command->getDescription());
        $this->assertSame(['demo:h'], $command->getAliases());
        $this->assertFalse($command->isHidden());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('greet'));
        $this->assertSame('World', $command->getDefinition()->getArgument('name')->getDefault());
    }

    /**
     * Test the hidden flag and the empty definitions are applied.
     */
    public function testAppliesTheHiddenFlagAndEmptyDefinitions(): void
    {
        $command = new MinimalCommand();

        $this->assertSame('demo:minimal', $command->getName());
        $this->assertTrue($command->isHidden());
        $this->assertSame('', $command->getDescription());
        $this->assertSame([], $command->getDefinition()->getArguments());
        $this->assertSame([], $command->getDefinition()->getOptions());
    }

    /**
     * Test a command works without an AsCommand attribute.
     */
    public function testWorksWithoutAnAsCommandAttribute(): void
    {
        $command = new NoAttributeCommand();

        $result = (new CommandTester($command))->run([]);

        $this->assertSame(Command::SUCCESS, $result->statusCode);
        $this->assertStringContainsString('no-attribute', $result->getDisplay());
    }

    /**
     * Test the default argument value is used when none is provided.
     */
    public function testUsesTheDefaultArgumentValueWhenNoneIsProvided(): void
    {
        $result = (new CommandTester(new DemoCommand()))->run([]);

        $this->assertSame(Command::SUCCESS, $result->statusCode);
        $this->assertStringContainsString('hello=World|false', $result->getDisplay());
    }

    /**
     * Test the provided argument and option values are used.
     */
    public function testUsesTheProvidedArgumentAndOptionValues(): void
    {
        $result = (new CommandTester(new DemoCommand()))->run([
            'name' => 'Ada',
            '--greet' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $result->statusCode);
        $this->assertStringContainsString('hello=Ada|true', $result->getDisplay());
    }

    /**
     * Test success is returned when invoke returns nothing.
     */
    public function testReturnsSuccessWhenInvokeReturnsNothing(): void
    {
        $result = (new CommandTester(new NullReturnCommand()))->run([]);

        $this->assertSame(Command::SUCCESS, $result->statusCode);
        $this->assertStringContainsString('null-returned', $result->getDisplay());
    }

    /**
     * Test a command can run another command through the application.
     */
    public function testRunsAnotherCommandThroughTheApplication(): void
    {
        $app = new Application('omega', '/');
        $console = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $console->addCommand(new CallerCommand());
        $console->addCommand(new TargetCommand());

        $result = (new CommandTester($console->find('demo:caller')))->run([]);

        $this->assertSame(Command::SUCCESS, $result->statusCode);
        $this->assertStringContainsString('caller-run', $result->getDisplay());
        $this->assertStringContainsString('target-run:World', $result->getDisplay());
    }

    /**
     * Test a failure is reported when the called command throws.
     */
    public function testReportsAFailureWhenTheCalledCommandThrows(): void
    {
        $app = new Application('omega', '/');
        $console = new ConsoleBranding($app, 'Omega Test:', '1.0.0');
        $console->addCommand(new CallerCommand());
        $console->addCommand(new ThrowingTargetCommand());

        $result = (new CommandTester($console->find('demo:caller')))->run([]);

        $this->assertSame(Command::FAILURE, $result->statusCode);
        $this->assertStringContainsString(
            "Unable to execute command 'demo:target': boom",
            $result->getDisplay()
        );
    }

    /**
     * Test a failure is reported when the command has no application.
     */
    public function testReportsAFailureWhenTheCommandHasNoApplication(): void
    {
        $result = (new CommandTester(new CallerCommand()))->run([]);

        $this->assertSame(Command::FAILURE, $result->statusCode);
        $this->assertStringContainsString(
            "Unable to execute command 'demo:target': no console application found",
            $result->getDisplay()
        );
    }

    /**
     * Test option suggested values are supported as an array.
     */
    public function testSupportsOptionSuggestedValuesAsAnArray(): void
    {
        $command = new SuggestedArrayCommand();

        $this->assertTrue($command->getDefinition()->getOption('sug')->hasCompletion());
    }

    /**
     * Test an argument configuration with too few elements throws.
     */
    public function testThrowsWhenAnArgumentConfigurationHasTooFewElements(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadArgumentCountCommand();
    }

    /**
     * Test an option configuration with too few elements throws.
     */
    public function testThrowsWhenAnOptionConfigurationHasTooFewElements(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadOptionCountCommand();
    }

    /**
     * Test an argument mode that is not an integer throws.
     */
    public function testThrowsWhenAnArgumentModeIsNotAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadArgumentModeCommand();
    }

    /**
     * Test an argument description that is not a string throws.
     */
    public function testThrowsWhenAnArgumentDescriptionIsNotAString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadArgumentDescriptionCommand();
    }

    /**
     * Test an option mode that is not an integer throws.
     */
    public function testThrowsWhenAnOptionModeIsNotAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadOptionModeCommand();
    }

    /**
     * Test an option description that is not a string throws.
     */
    public function testThrowsWhenAnOptionDescriptionIsNotAString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadOptionDescriptionCommand();
    }

    /**
     * Test an invalid option shortcut throws.
     */
    public function testThrowsWhenAnOptionShortcutIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadOptionShortcutCommand();
    }

    /**
     * Test suggested values that are neither an array nor a closure throw.
     */
    public function testThrowsWhenSuggestedValuesAreNeitherAnArrayNorAClosure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadSuggestedValuesCommand();
    }

    /**
     * Test an argument configuration that is not an array throws.
     */
    public function testThrowsWhenAnArgumentConfigurationIsNotAnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadArgumentNotArrayCommand();
    }

    /**
     * Test an argument configuration with too many elements throws.
     */
    public function testThrowsWhenAnArgumentConfigurationHasTooManyElements(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadArgumentTooManyCommand();
    }

    /**
     * Test an option configuration that is not an array throws.
     */
    public function testThrowsWhenAnOptionConfigurationIsNotAnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadOptionNotArrayCommand();
    }

    /**
     * Test an option configuration with too many elements throws.
     */
    public function testThrowsWhenAnOptionConfigurationHasTooManyElements(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BadOptionTooManyCommand();
    }

    /**
     * Test a null shortcut, an array shortcut and a four element option
     * configuration are accepted.
     */
    public function testAcceptsANullShortcutAnArrayShortcutAndAFourElementOptionConfiguration(): void
    {
        $command = new ShortcutOptionsCommand();

        $this->assertTrue($command->getDefinition()->hasOption('o1'));
        $this->assertSame('default', $command->getDefinition()->getOption('o1')->getDefault());
        $this->assertSame('a|b', $command->getDefinition()->getOption('o2')->getShortcut());
    }

    /**
     * Test a closure is accepted as suggested values when registered directly.
     */
    public function testAcceptsAClosureAsSuggestedValuesWhenRegisteredDirectly(): void
    {
        $command = new DemoCommand();

        (new ReflectionMethod(AbstractCommand::class, 'registerOption'))->invoke(
            $command,
            'pick',
            ['p', InputOption::VALUE_OPTIONAL, 'Pick', null, static fn (array $values): array => $values],
        );

        $this->assertTrue($command->getDefinition()->hasOption('pick'));
    }
}
