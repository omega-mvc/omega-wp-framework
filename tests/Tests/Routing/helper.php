<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * The global WordPress function doubles that used to live here now live in
 * tests/Tests/Routing/WordPressFunctions.php, which tests/bootstrap.php
 * requires instead.
 *
 * They had to move because this file is registered in the Composer
 * `autoload-dev.files` section, so it is loaded while the autoloader boots,
 * i.e. before PHPStan executes the bootstrap files declared by
 * szepeviktor/phpstan-wordpress. `php-stubs/wordpress-stubs` is one of those
 * bootstrap files and declares the same global functions, so PHPStan aborted
 * with "Cannot redeclare function add_menu_page()". Loading the doubles from
 * tests/bootstrap.php only keeps a real WordPress runtime, or the stub
 * declarations, from being shadowed.
 *
 * This file is kept because vendor/composer/autoload_files.php still requires
 * it, and regenerating the Composer autoloader is not something this package
 * does on its own. Once `composer dump-autoload` runs without this path listed
 * in composer.json, the file can be deleted.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);
