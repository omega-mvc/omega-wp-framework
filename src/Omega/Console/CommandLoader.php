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

use Omega\Application\ApplicationInterface;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

use function array_keys;
use function sprintf;

/**
 * Loads console commands using the Omega container.
 *
 * This loader provides lazy instantiation of commands by delegating
 * their creation to the Omega application container. Commands are
 * defined as a map of command names to their corresponding class names.
 *
 * This allows Symfony Console to remain responsible for command
 * resolution while Omega controls dependency injection and lifecycle.
 *
 * @category  Omega
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
final class CommandLoader implements CommandLoaderInterface
{
    /**
     * CommandLoader constructor.
     *
     * @param ApplicationInterface                 $app      The Omega application container.
     * @param array<string, class-string<Command>> $commands Map of command names to command class names.
     * @return void
     */
    public function __construct(
        protected ApplicationInterface $app,
        protected array $commands
    ) {
    }

    /**
     * Returns a command instance by its name.
     *
     * The command is resolved through the Omega container,
     * allowing full dependency injection support.
     *
     * @param string $name The command name.
     * @return Command The resolved command instance.
     * @throws ReflectionException If a class cannot be reflected.
     */
    public function get(string $name): Command
    {
        if (!$this->has($name)) {
            throw new CommandNotFoundException(
                sprintf(
                    'Command "%s" is not defined.',
                    $name
                )
            );
        }

        /** @var Command $command */
        $command = $this->app->resolve($this->commands[$name]);

        if ($command instanceof AbstractCommand) {
            $command->app = $this->app;
        }

        return $command;
    }

    /**
     * Checks whether a command exists for the given name.
     *
     * @param string $name The command name.
     * @return bool True if the command exists, false otherwise.
     */
    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * Returns all available command names.
     *
     * @return array<int, string> List of command names.
     */
    public function getNames(): array
    {
        return array_keys($this->commands);
    }
}
