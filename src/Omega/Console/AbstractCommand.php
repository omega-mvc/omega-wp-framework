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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Throwable;

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

    /** @var array Alternative names that can be used to execute the command */
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
        try {
            $command = $this->getApplication()->find($commandName);
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
        $reflection = new ReflectionClass($this);
        $attribute = $reflection->getAttributes(AsCommand::class)[0] ?? null;

        if (!$attribute) {
            return;
        }

        $settings = $attribute->newInstance();

        $this->setName($settings->name);

        if ($settings->description) {
            $this->setDescription($settings->description);
        }

        $this->setAliases($settings->aliases);
        $this->setHidden($settings->hidden);

        foreach ($settings->arguments as $name => $config) {
            if (!is_array($config) || count($config) < 2 || count($config) > 3) {
                throw new InvalidArgumentException(
                    "Argument configuration for '$name' must be an array with 2 or 3 elements: "
                    . "[mode:int, description:string, default?]"
                );
            }

            [$mode, $description] = $config;
            /** @noinspection PhpUnusedLocalVariableInspection */
            $default = $config[2] ?? null;

            if (!is_int($mode)) {
                throw new InvalidArgumentException("Argument '$name': mode must be an integer.");
            }
            if (!is_string($description)) {
                throw new InvalidArgumentException("Argument '$name': description must be a string.");
            }

            $this->addArgument($name, $config[0], $config[1], $config[2] ?? null);
        }

        foreach ($settings->options as $name => $config) {
            if (!is_array($config) || count($config) < 3 || count($config) > 5) {
                throw new InvalidArgumentException(
                    "Option configuration for '$name' must be an array with 3-5 elements: "
                    . "[shortcut:string|array|null, mode:int, description:string, default?, "
                    . "suggestedValues?]"
                );
            }

            $shortcut = $config[0];
            $mode = $config[1];
            $description = $config[2];
            /** @noinspection PhpUnusedLocalVariableInspection */
            $default         = $config[3] ?? null;
            $suggestedValues = $config[4] ?? [];

            if (!is_int($mode)) {
                throw new InvalidArgumentException("Option '$name': mode must be an integer.");
            }
            if (!is_string($description)) {
                throw new InvalidArgumentException("Option '$name': description must be a string.");
            }
            if (!is_null($shortcut) && !is_string($shortcut) && !is_array($shortcut)) {
                throw new InvalidArgumentException("Option '$name': shortcut must be string, array or null.");
            }
            if (!is_array($suggestedValues) && !$suggestedValues instanceof Closure) {
                throw new InvalidArgumentException("Option '$name': suggestedValues must be array or Closure.");
            }
            $this->addOption($name, $config[0], $config[1], $config[2], $config[3] ?? null, $config[4] ?? []);
        }
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
