<?php

/**
 * Part of Omega - Tests\Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

declare(strict_types=1);

namespace Tests\Console\Fixtures;

/**
 * Static helpers shared by the console test suite.
 */
final class ConsoleSupport
{
    /**
     * Run the console handle() logic in a subprocess and return its exit code.
     *
     * @param array<int, string> $tokens
     */
    public static function runHandleInSubprocess(
        string $autoload,
        string $base,
        array $tokens,
        mixed $input,
        string $outputCode,
        string $shell,
    ): int {
        $inputCode = match (true) {
            $input === null => 'null',
            $input === 'array-input' => 'new \Symfony\Component\Console\Input\ArrayInput('
                . var_export($tokens, true) . ')',
            default => var_export($input, true),
        };

        $autoloadCode = var_export($autoload, true);
        $baseCode = var_export($base, true);
        $argvCode = var_export($tokens, true);

        $code = "require {$autoloadCode}; \$_SERVER['argv'] = {$argvCode};"
            . " \$app = new \Omega\Application\Application('omega', {$baseCode});"
            . ' $console = new \Omega\Console\ConsoleApplication($app);'
            . " \$output = {$outputCode};"
            . " \$exit = \$console->handle({$inputCode}, \$output);"
            . ' echo $exit;';

        $env = $shell === '' ? 'SHELL= ' : 'SHELL=' . escapeshellarg($shell) . ' ';
        $command = $env . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code);

        exec($command, $lines, $status);

        return $status;
    }

    /**
     * Create a temporary console base path.
     */
    public static function newConsoleBase(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omega-console-' . bin2hex(random_bytes(4));
        mkdir($base, 0777, true);

        return $base;
    }

    /**
     * Remove the temporary console base path.
     */
    public static function removeConsoleBase(string $base): void
    {
        foreach (['/bootstrap/cache/commands.php', '/bootstrap/cache', '/bootstrap', ''] as $suffix) {
            $path = $base . $suffix;

            if (is_file($path)) {
                @unlink($path);
                continue;
            }

            if (is_dir($path)) {
                @rmdir($path);
            }
        }
    }
}
