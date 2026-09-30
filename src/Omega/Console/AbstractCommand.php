<?php

/**
 * Part of Omega - Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Omega\Console;

use Closure;
use Omega\Application\ApplicationInterface;
use Omega\Console\Attribute\AsCommand;
use Omega\Console\Exceptions\InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Throwable;

use function array_keys;
use function array_map;
use function count;
use function is_array;
use function is_int;
use function is_null;
use function is_string;

/**
 * Base class for all console commands in Omega.
 *
 * This class wraps Symfony Command, providing an extended execution flow
 * with a dedicated Style helper for consistent console output formatting.
 * Concrete commands should implement the __invoke() method to define logic.
 *
 * @category  Omega
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
abstract class AbstractCommand extends Command
{
    /** @var InputInterface Current input instance */
    protected InputInterface $input;

    /** @var OutputInterface Current output instance */
    protected OutputInterface $output;

    /** @var Style Console output helper for styled messages */
    protected Style $io;

    /** Provides access to terminal I/O and interaction utilities across the command. */
    protected Terminal $terminal;

    /** @var ApplicationInterface The Omega application instance */
    public ApplicationInterface $app { // phpcs:ignore PSR2.Classes.PropertyDeclaration -- PHP 8.4 property hook
        set(ApplicationInterface $app) { // phpcs:ignore PSR2.Classes.PropertyDeclaration -- PHP 8.4 property hook
            $this->app = $app; // phpcs:ignore PSR2.Classes.PropertyDeclaration -- PHP 8.4 property hook
        }
    }

    /** @var string Command name used to invoke the command from the CLI */
    protected string $name;

    /** @var string|null Short description displayed in the command list */
    protected ?string $description = null;

    /** @var array<int|string, string> Alternative names that can be used to execute the command */
    protected array $aliases = [];

    /** @var bool Whether the command should be hidden from the command list */
    protected bool $hidden = false;

    /**
     * Executes the console command.
     *
     * Initializes input, output, and Style helper, then calls __invoke().
     *
     * @param InputInterface $input The input object
     * @param OutputInterface $output The output object
     * @return int Exit code from __invoke()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input    = $input;
        $this->output   = $output;
        $this->io       = new Style($input, $output);
        $this->terminal = new Terminal();

        // @todo Check if all commands return int
        return $this->__invoke() ?? self::SUCCESS;
    }

    /**
     * Runs another console command internally.
     *
     * @param string $commandName Name of the command to execute (e.g., 'migrate:fresh')
     * @param array<string, mixed> $parameters Command arguments and options
     * @return int Exit code of the executed command
     * @throws Throwable Never thrown, handled internally
     */
    protected function call(string $commandName, array $parameters = []): int
    {
        $application = $this->getApplication();

        if (!$application instanceof Application) {
            $this->io->error('Unable to execute command \'' . $commandName . '\': no console application found.');

            return self::FAILURE;
        }

        try {
            $command = $application->find($commandName);
            $parameters['command'] = $commandName;
            $input = new ArrayInput($parameters);

            return $command->run($input, $this->output);
        } catch (Throwable $e) {
            $this->io->error('Unable to execute command \'' . $commandName . '\': ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Main logic of the command.
     *
     * Concrete commands must implement this method.
     *
     * @return int|void Exit code or nothing
     */
    abstract public function __invoke();

    /**
     * Configures the command using the AsCommand attribute.
     *
     * Reads metadata via reflection and applies name, description,
     * aliases, visibility, arguments, and options to the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $attributes = (new ReflectionClass($this))->getAttributes(AsCommand::class);

        if ($attributes === []) {
            return;
        }

        $settings = $attributes[0]->newInstance();

        $this->setName($settings->name);

        if ($settings->description) {
            $this->setDescription($settings->description);
        }

        $this->setAliases($settings->aliases);
        $this->setHidden($settings->hidden);

        array_map(
            fn (mixed $argumentName, mixed $argumentConfig) => $this->registerArgument(
                (string) $argumentName,
                $argumentConfig
            ),
            array_keys($settings->arguments),
            $settings->arguments
        );

        array_map(
            fn (mixed $optionName, mixed $optionConfig) => $this->registerOption(
                (string) $optionName,
                $optionConfig
            ),
            array_keys($settings->options),
            $settings->options
        );
    }

    /**
     * Register a single command argument definition.
     *
     * @param string $argumentName Name of the argument as defined in the AsCommand attribute.
     * @param mixed  $config       Argument configuration array: [mode, description, default?].
     * @return void
     * @throws InvalidArgumentException When the argument configuration is invalid.
     */
    private function registerArgument(string $argumentName, mixed $config): void
    {
        $this->assertArgumentShape($argumentName, $config);

        [$mode, $description] = $config;

        $this->assertArgumentMode($argumentName, $mode);
        $this->assertArgumentDescription($argumentName, $description);

        $this->addArgument($argumentName, $mode, $description, $config[2] ?? null);
    }

    /**
     * Validate the shape of an argument configuration.
     *
     * @param string $argumentName Name of the argument as defined in the AsCommand attribute.
     * @param mixed  $config       Argument configuration value.
     * @return void
     * @throws InvalidArgumentException When the argument configuration is invalid.
     * @phpstan-assert array{0: mixed, 1: mixed, 2?: mixed} $config
     */
    private function assertArgumentShape(string $argumentName, mixed $config): void
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException(
                "Argument configuration for '$argumentName' must be an array with 2 or 3 elements: "
                . "[mode:int, description:string, default?]"
            );
        }

        $size = count($config);

        if ($size < 2) {
            throw new InvalidArgumentException(
                "Argument configuration for '$argumentName' must be an array with 2 or 3 elements: "
                . "[mode:int, description:string, default?]"
            );
        }

        if ($size > 3) {
            throw new InvalidArgumentException(
                "Argument configuration for '$argumentName' must be an array with 2 or 3 elements: "
                . "[mode:int, description:string, default?]"
            );
        }
    }

    /**
     * Assert that a mode value is an integer.
     *
     * @param string $entity  Entity type used in the error message ('Argument' or 'Option').
     * @param string $name    Definition name used in the error message.
     * @param mixed  $mode    Mode value to validate.
     * @return void
     * @throws InvalidArgumentException When the mode is not an integer.
     * @phpstan-assert int $mode
     */
    private function assertMode(string $entity, string $name, mixed $mode): void
    {
        if (!is_int($mode)) {
            throw new InvalidArgumentException("$entity '$name': mode must be an integer.");
        }
    }

    /**
     * Assert that a description value is a string.
     *
     * @param string $entity      Entity type used in the error message ('Argument' or 'Option').
     * @param string $name        Definition name used in the error message.
     * @param mixed  $description Description value to validate.
     * @return void
     * @throws InvalidArgumentException When the description is not a string.
     * @phpstan-assert string $description
     */
    private function assertDescription(string $entity, string $name, mixed $description): void
    {
        if (!is_string($description)) {
            throw new InvalidArgumentException("$entity '$name': description must be a string.");
        }
    }

    /**
     * Assert that the mode and description of an argument are valid.
     *
     * @param string $argumentName Name of the argument.
     * @param mixed  $mode         Mode value to validate.
     * @return void
     * @throws InvalidArgumentException When the mode is not an integer.
     * @phpstan-assert int $mode
     */
    private function assertArgumentMode(string $argumentName, mixed $mode): void
    {
        $this->assertMode('Argument', $argumentName, $mode);
    }

    /**
     * Assert that the argument description is valid.
     *
     * @param string $argumentName Name of the argument.
     * @param mixed  $description  Description value to validate.
     * @return void
     * @throws InvalidArgumentException When the description is not a string.
     * @phpstan-assert string $description
     */
    private function assertArgumentDescription(string $argumentName, mixed $description): void
    {
        $this->assertDescription('Argument', $argumentName, $description);
    }

    /**
     * Register a single command option definition.
     *
     * @param string $optionName Name of the option as defined in the AsCommand attribute.
     * @param mixed  $config     Option configuration array: [shortcut, mode, description, default?, suggestedValues?].
     * @return void
     * @throws InvalidArgumentException When the option configuration is invalid.
     */
    private function registerOption(string $optionName, mixed $config): void
    {
        $this->assertOptionShape($optionName, $config);

        [$default, $suggestedValues] = match (count($config)) {
            5 => [$config[3] ?? null, $config[4] ?? []],
            4 => [$config[3] ?? null, []],
            default => [null, []],
        };

        $this->assertOptionMode($optionName, $config[1]);
        $this->assertOptionDescription($optionName, $config[2]);
        $this->assertOptionShortcut($optionName, $config[0]);
        $this->assertOptionSuggestedValues($optionName, $suggestedValues);

        $this->addOption($optionName, $config[0], $config[1], $config[2], $default, $suggestedValues);
    }

    /**
     * Validate the shape of an option configuration.
     *
     * @param string $optionName Name of the option as defined in the AsCommand attribute.
     * @param mixed  $config     Option configuration value.
     * @return void
     * @throws InvalidArgumentException When the option configuration is invalid.
     * @phpstan-assert array{0: mixed, 1: mixed, 2: mixed, 3?: mixed, 4?: mixed} $config
     */
    private function assertOptionShape(string $optionName, mixed $config): void
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException(
                "Option configuration for '$optionName' must be an array with 3-5 elements: "
                . "[shortcut:string|array|null, mode:int, description:string, default?, "
                . "suggestedValues?]"
            );
        }

        $size = count($config);

        if ($size < 3) {
            throw new InvalidArgumentException(
                "Option configuration for '$optionName' must be an array with 3-5 elements: "
                . "[shortcut:string|array|null, mode:int, description:string, default?, "
                . "suggestedValues?]"
            );
        }

        if ($size > 5) {
            throw new InvalidArgumentException(
                "Option configuration for '$optionName' must be an array with 3-5 elements: "
                . "[shortcut:string|array|null, mode:int, description:string, default?, "
                . "suggestedValues?]"
            );
        }
    }

    /**
     * Assert that the mode of an option is valid.
     *
     * @param string $optionName Name of the option.
     * @param mixed  $mode       Mode value to validate.
     * @return void
     * @throws InvalidArgumentException When the mode is not an integer.
     * @phpstan-assert int $mode
     */
    private function assertOptionMode(string $optionName, mixed $mode): void
    {
        $this->assertMode('Option', $optionName, $mode);
    }

    /**
     * Assert that the option description is valid.
     *
     * @param string $optionName  Name of the option.
     * @param mixed  $description Description value to validate.
     * @return void
     * @throws InvalidArgumentException When the description is not a string.
     * @phpstan-assert string $description
     */
    private function assertOptionDescription(string $optionName, mixed $description): void
    {
        $this->assertDescription('Option', $optionName, $description);
    }

    /**
     * Assert that the option shortcut is a string, an array or null.
     *
     * @param string $optionName Name of the option.
     * @param mixed  $shortcut   Shortcut value to validate.
     * @return void
     * @throws InvalidArgumentException When the shortcut is invalid.
     * @phpstan-assert string|array<string>|null $shortcut
     */
    private function assertOptionShortcut(string $optionName, mixed $shortcut): void
    {
        if ($shortcut === null) {
            return;
        }

        if (is_string($shortcut)) {
            return;
        }

        if (is_array($shortcut)) {
            return;
        }

        throw new InvalidArgumentException("Option '$optionName': shortcut must be string, array or null.");
    }

    /**
     * Assert that the suggested values are an array or a Closure.
     *
     * @param string $optionName       Name of the option.
     * @param mixed  $suggestedValues Suggested values to validate.
     * @return void
     * @throws InvalidArgumentException When the suggested values are invalid.
     * @phpstan-assert array<int, mixed>|\Closure $suggestedValues
     */
    private function assertOptionSuggestedValues(string $optionName, mixed $suggestedValues): void
    {
        if (is_array($suggestedValues)) {
            return;
        }

        if ($suggestedValues instanceof \Closure) {
            return;
        }

        throw new InvalidArgumentException("Option '$optionName': suggestedValues must be array or Closure.");
    }

    /**
     * Retrieves the value of an argument.
     *
     * @param string $key Argument name
     * @return mixed Value of the argument
     */
    protected function getArgument(string $key): mixed
    {
        return $this->input->getArgument($key);
    }

    /**
     * Retrieves the value of an option.
     *
     * @param string $key Option name
     * @return mixed Value of the option
     */
    protected function getOption(string $key): mixed
    {
        return $this->input->getOption($key);
    }
}
