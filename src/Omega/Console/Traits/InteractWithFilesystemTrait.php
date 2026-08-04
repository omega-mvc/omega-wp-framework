<?php

/**
 * Part of Omega - Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Console\Traits;

use Symfony\Component\Finder\Finder;

use function array_map;
use function is_dir;
use function iterator_to_array;

/**
 * Trait providing filesystem utilities for console commands or services.
 *
 * This trait allows searching for files recursively in a directory,
 * using one or more patterns and optional exclusions.
 * It is intended to be reusable in any context that requires
 * filesystem interaction without coupling to specific commands.
 *
 * Example usage:
 * ```
 * $files = $this->findFiles('/path/to/dir', ['*.php', '*.twig'], ['Test*.php']);
 * ```
 *
 * @category   Omega
 * @package    Console
 * @subpackage Traits
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
trait InteractWithFilesystemTrait
{
    /**
     * Recursively searches for files in a directory matching given patterns.
     *
     * @param string               $directory Directory to search in.
     * @param string|string[]      $patterns  A pattern or an array of patterns to match (e.g., '*.php').
     * @param string[]             $exclude   An array of patterns to exclude (e.g., ['Test*.php']).
     * @return array<int,string>             An array of absolute paths of the matched files.
     */
    protected function findFiles(string $directory, string|array $patterns = '*', array $exclude = []): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($directory);

        foreach ((array)$patterns as $pattern) {
            $finder->name($pattern);
        }

        foreach ($exclude as $exPattern) {
            $finder->notName($exPattern);
        }

        return array_map(
            static fn($file) => $file->getRealPath(),
            iterator_to_array($finder, false)
        );
    }
}
