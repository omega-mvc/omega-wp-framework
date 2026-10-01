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

use Exception;
use Omega\Application\ApplicationInterface;
use Omega\Console\Attribute\AsCommand;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function array_values;
use function array_walk;
use function class_exists;
use function file_exists;
use function getenv;
use function is_array;
use function is_dir;
use function iterator_to_array;
use function putenv;
use function Omega\Application\slash;
use function str_contains;

/**
 * Console application entry point for Omega.
 *
 * This class acts as a bridge between the Omega application container
 * and the Symfony Console component. It is responsible for bootstrapping
 * the application, resolving configured commands, and delegating execution
 * to the Symfony console runtime.
 *
 * The console lifecycle is:
 * 1. Bootstrap the application (providers, config, etc.)
 * 2. Resolve command classes from configuration
 * 3. Register commands into Symfony Console
 * 4. Execute the console application
 *
 * This implementation keeps Omega decoupled from the console engine,
 * allowing Symfony Console to handle input parsing, command resolution,
 * and execution flow.
 *
 * @category  Omega
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
class ConsoleApplication
{
    /** @ var array<int, class-string> The list of bootstrapper classes to run during initialization. */
    /**protected array $bootstrappers = [
        ConfigBootstrapper::class,
        FacadeBootstrapper::class,
        RegisterProviders::class,
        BootProviders::class,
    ];*/

    /**
     * Create a new Console instance.
     *
     * @param ApplicationInterface $app The application container.
     * @return void
     */
    public function __construct(protected ApplicationInterface $app)
    {
    }

    /**
     * Handle a console request.
     *
     * This method bootstraps the application (if not already bootstrapped),
     * prepares input and output instances, registers all configured commands,
     * and delegates execution to the Symfony Console application.
     *
     * @param array<int, string>|InputInterface|null $input Raw CLI arguments or a pre-built input instance.
     * @param OutputInterface|null $output Output instance; defaults to ConsoleOutput if null.
     * @return int Exit status code returned by the console application.
     * @throws Exception
     */
    public function handle(array|InputInterface|null $input = null, OutputInterface|null $output = null): int
    {
        //$this->bootstrap();

        $input  = is_array($input) ? new ArgvInput(array_values($input)) : ($input ?? new ArgvInput());
        $output = $output ?? new ConsoleOutput();

        $shell = getenv('SHELL');

        if (!$this->isSupportedShell($shell)) {
            putenv('SHELL=/bin/bash');
        }

        $omega = new ConsoleBranding(
            $this->app,
            $this->app->getName() . ' Framework:',
            $this->app->getVersion()
        );

        $this->configureCommandLoader($omega);

        $omega->setAutoExit(false);

        return $omega->run($input, $output);
    }

    /**
     * Determine whether the detected shell can host the Omega console.
     *
     * @param string|false $shell The SHELL environment value, or false when unset.
     * @return bool True when the shell is a supported interactive shell.
     */
    private function isSupportedShell(string|false $shell): bool
    {
        if (!$shell) {
            return false;
        }

        if (str_contains($shell, 'bash')) {
            return true;
        }

        if (str_contains($shell, 'zsh')) {
            return true;
        }

        return str_contains($shell, 'fish');
    }

    /**
     * Bootstrap the application if it has not already been bootstrapped.
     *
     * Executes the configured bootstrappers, which typically register
     * configuration, facades, and service providers into the container.
     *
     * @return void
     */
    /**protected function bootstrap(): void
    {
        if (!$this->app->bootstrapped) {
            $this->app->bootstrapWith($this->bootstrappers);
        }
    }*/

    /**
     * Configure the Symfony Console command loader.
     *
     * Retrieves the command map from the configuration repository and assigns
     * an OmegaCommandLoader to the Symfony Console instance. This enables
     * lazy command resolution through the Omega container.
     *
     * Expected configuration format:
     *
     * [
     *     'route:list' => RouteCommand::class,
     *     'cache:clear' => CacheClearCommand::class,
     * ]
     *
     * @param Application $console The Symfony Console application instance.
     * @return void
     */
    protected function configureCommandLoader(Application $console): void
    {
        $cacheFile = $this->app->getApplicationCachePath() . 'commands.php';

        if (file_exists($cacheFile)) {
            $merged = require $cacheFile;

            $commands = is_array($merged) ? $merged : [];
        } else {
            $commands = $this->discoverCommands();
        }

        /** @var array<string, class-string<Command>> $commands */
        $console->setCommandLoader(new CommandLoader($this->app, $commands));
    }

    /**
     * Discovers all available console commands by scanning configured directories.
     *
     * Iterates through predefined command paths, reflects each class, and extracts
     * metadata from the AsCommand attribute to build a command name to class map.
     *
     * @return array<string, class-string> Discovered command name to class map
     */
    public function discoverCommands(): array
    {
        /** @var string $frameworkCommands */
        $frameworkCommands   = slash(path: '/Commands');
        /** @var string $applicationCommands */
        $applicationCommands = slash(path: '/app/Commands');

        $commandPaths = [
            'Omega\\Console\\Commands\\' => __DIR__ . $frameworkCommands,
            'App\\Console\\Commands\\'   => $this->app->getBasePath() . $applicationCommands,
        ];

        $commands = [];

        array_walk($commandPaths, function (string $path, string $namespace) use (&$commands): void {
            if (!is_dir($path)) {
                return;
            }

            $this->discoverCommandsInPath($namespace, $path, $commands);
        });

        return $commands;
    }

    /**
     * Discover the command classes found under a single namespace and directory.
     *
     * @param string $namespace The namespace prefix matching the scanned directory.
     * @param string $path The directory holding the command classes.
     * @param array<string, class-string> $commands The command map being built.
     * @return void
     */
    private function discoverCommandsInPath(string $namespace, string $path, array &$commands): void
    {
        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($path);

        /** @var array<int, SplFileInfo> $files */
        $files = iterator_to_array($finder, false);

        array_walk($files, function (SplFileInfo $file) use ($namespace, &$commands): void {
            $className = $namespace . $file->getBasename('.php');

            if (!class_exists($className)) {
                return;
            }

            $this->registerDiscoveredCommand($className, $commands);
        });
    }

    /**
     * Register a discovered command class using its AsCommand metadata.
     *
     * @param class-string $className The fully qualified command class name.
     * @param array<string, class-string> $commands The command map being built.
     * @return void
     */
    private function registerDiscoveredCommand(string $className, array &$commands): void
    {
        $reflection = new ReflectionClass($className);
        $attribute  = $reflection->getAttributes(AsCommand::class)[0] ?? null;

        if (!$attribute) {
            return;
        }

        $instance = $attribute->newInstance();

        $commands[$instance->name] = $className;

        /** @var array<int|string, string> $aliases */
        $aliases = $instance->aliases;
        array_walk($aliases, static function (string $alias) use ($className, &$commands): void {
            $commands[$alias] = $className;
        });
    }
}
