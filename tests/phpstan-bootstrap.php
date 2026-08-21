<?php

/**
 * PHPStan bootstrap file.
 *
 * Defines WordPress constants that are not declared in the php-stubs/wordpress-stubs
 * package but are required for level-10 static analysis.
 */

declare(strict_types=1);

defined('ABSPATH') || define('ABSPATH', '/tmp/wp/');
defined('WP_PLUGIN_DIR') || define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins');
defined('WP_CONTENT_DIR') || define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
