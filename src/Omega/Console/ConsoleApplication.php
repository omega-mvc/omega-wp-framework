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
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use function class_exists;
use function file_exists;
use function getenv;
use function is_array;
use function is_dir;
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

        $input  = is_array($input) ? new ArgvInput($input) : ($input ?? new ArgvInput());
        $output = $output ?? new ConsoleOutput();

        $shell = getenv('SHELL');

        if (!$shell || !str_contains($shell, 'bash') && !str_contains($shell, 'zsh') && !str_contains($shell, 'fish')) {
            putenv('SHELL=/bin/bash');
        }

        $omega = new ConsoleBranding(
            $this->app,
            $this->app->getName() . ' Framework:',
            $this->app->getVersion()
        );

        $this->configureCommandLoader($omega);

        return $omega->run($input, $output);
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

        $merged = file_exists($cacheFile)
            ? require $cacheFile
            : $this->discoverCommands();

        $console->setCommandLoader(new CommandLoader($this->app, $merged));
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
        $commandPaths = [
            'Omega\\Console\\Commands\\' => __DIR__ . slash(path: '/Commands'),
            'App\\Console\\Commands\\'   => $this->app->getBasePath() . slash(path: '/app/Commands'),
        ];

        $commands = [];

        foreach ($commandPaths as $namespace => $path) {
            if (!is_dir($path)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->name('*Command.php')->in($path);

            foreach ($finder as $file) {
                $className = $namespace . $file->getBasename('.php');
                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);
                $attribute = $reflection->getAttributes(AsCommand::class)[0] ?? null;

                if ($attribute) {
                    $instance = $attribute->newInstance();

                    $commands[$instance->name] = $className;

                    if (!empty($instance->aliases)) {
                        foreach ($instance->aliases as $alias) {
                            $commands[$alias] = $className;
                        }
                    }
                }
            }
        }

        return $commands;
    }
}
