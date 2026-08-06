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
use Symfony\Component\Console\Application as SymfonyConsole;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function file_exists;
use function in_array;
use function memory_get_usage;
use function round;
use function sprintf;

use const PHP_VERSION;

/**
 * Class ConsoleLogo
 *
 * Custom Symfony Console application for the Omega framework.
 *
 * This class extends Symfony's Console Application to provide a branded
 * console interface with the Omega ASCII logo, runtime information (environment,
 * debug status, PHP version, memory usage), and intelligent handling of
 * silent commands (like 'list' or '--help').
 *
 * Key features:
 * - Displays a custom ASCII logo when executing non-silent commands.
 * - Renders runtime information for environment, debug mode, PHP version, and memory usage.
 * - Determines which commands are silent and skips rendering the header for them.
 * - Provides utility to format memory usage in human-readable format.
 * - Fully integrates with Omega's Application container for resolving runtime
 *   environment and configuration.
 *
 * This class is intended to be the main entry point for Omega console commands,
 * replacing the standard Symfony Console application while preserving all
 * Symfony features such as command registration, input/output handling, and
 * error management.
 *
 * @category  Omega
 * @package   Console
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
final class ConsoleBranding extends SymfonyConsole
{
    /**
     * ConsoleLogo constructor.
     *
     * Initializes the custom Omega console application with logo and runtime info.
     *
     * @param ApplicationInterface $app     The Omega application instance, used to retrieve environment, debug mode, and other runtime info.
     * @param string               $name    The name of the console application.
     * @param string               $version The version of the console application.
     * @return void
     */
    public function __construct(
        protected ApplicationInterface $app,
        string $name,
        string $version
    ) {
        parent::__construct($name, $version);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable If any unexpected error occurs during command execution.
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isSilentCommand($input)) {
            $this->renderHeader($output);
        }

        return parent::doRun($input, $output);
    }

    /**
     * Render full console header including the ASCII logo and runtime environment info.
     *
     * @param OutputInterface $output The output stream where the header will be written.
     * @return void
     */
    protected function renderHeader(OutputInterface $output): void
    {
        $this->renderLogo($output);
        $this->renderRuntimeInfo($output);

        $output->writeln('');
    }

    /**
     * Render the Omega ASCII logo in the console.
     *
     * @param OutputInterface $output The output stream used to print the logo.
     * @return void
     */
    protected function renderLogo(OutputInterface $output): void
    {
        $output->writeln("
<fg=cyan;options=bold>   ____   __  __  ______  ______ ___   </>
<fg=cyan;options=bold>  / __ \ /  |/  |/ ____// ____//   |   </>
<fg=cyan;options=bold> / / / // /|_/ // __/  / / __ / /| |   </>
<fg=cyan;options=bold>/ /_/ // /  / // /___ / /_/ // ___ |   </>
<fg=cyan;options=bold>\____//_/  /_//_____/ \____//_/  |_|   </>
");
    }

    /**
     * Render runtime information including environment, debug status, PHP version, and memory usage.
     *
     * @param OutputInterface $output The output stream used to write runtime info.
     * @return void
     */
    protected function renderRuntimeInfo(OutputInterface $output): void
    {
        $env    = $this->app->getEnvironment() ?? 'unknown';
        $debug  = $this->app->isDebugMode() ? 'ON' : 'OFF';
        $php    = PHP_VERSION;
        $memory = $this->formatBytes(memory_get_usage(true));

        $cacheFile = $this->app->getApplicationCachePath() . 'commands.php';
        $isCached  = file_exists($cacheFile) ? 'YES' : 'NO';

        $output->writeln(sprintf(
            '<fg=gray> Environment:</> <fg=yellow>%s</>  |  <fg=gray>Debug:</> <fg=%s>%s</>  |  <fg=gray>PHP:</> %s  |  <fg=gray>Command Cache:</> <fg=%s>%s</>  |  <fg=gray>Memory:</> %s',
            $env,
            $debug === 'ON' ? 'green' : 'red', $debug,
            $php,
            $isCached === 'YES' ? 'green' : 'yellow', $isCached,
            $memory
        ));
    }

    /**
     * Determine if the current command should suppress the console header.
     *
     * @param InputInterface $input The input arguments provided to the console.
     * @return bool
     */
    protected function isSilentCommand(InputInterface $input): bool
    {
        if ($input->hasParameterOption(['--version', '-V', '--quiet', '-q'])) {
            return true;
        }

        $name = $input->getFirstArgument();

        return in_array($name, ['list', 'help', 'completion'], true);
    }

    /**
     * Format a byte count into a human-readable string.
     *
     * Converts bytes into the largest unit (B, KB, MB, GB) while keeping two decimal places.
     *
     * @param int $bytes The number of bytes to format.
     * @return string Human-readable formatted string, e.g., '1.23 MB'.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < 3; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
