<?php

declare(strict_types=1);

namespace Omega\Application;

use function array_map;
use function function_exists;
use function is_array;
use function str_replace;

use const DIRECTORY_SEPARATOR;

if (!function_exists('slash')) {
	/**
	 * Normalize filesystem path separators.
	 *
	 * This helper converts all forward slashes (`/`) in the given path
	 * to the platform-specific directory separator (`DIRECTORY_SEPARATOR`).
	 *
	 * It supports both string and array inputs. When an array is provided,
	 * the normalization is applied recursively to each element.
	 *
	 * This function does not alter the semantic meaning of the path,
	 * but ensures consistency across different operating systems.
	 *
	 * @param string|array $path The path or list of paths to normalize.
	 * @return string|array The normalized path(s) with correct directory separators.
	 */
	function slash(string|array $path): string|array
	{
		if ( is_array($path)) {
			return array_map(fn($p) => str_replace('/', DIRECTORY_SEPARATOR, $p), $path);
		}

		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}
}