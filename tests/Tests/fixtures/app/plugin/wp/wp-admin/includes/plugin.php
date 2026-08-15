<?php

/**
 * Omega test fixture that simulates the WordPress plugin-data parser loader.
 *
 * Loaded by ApplicationPlugin::loadFileDataParser() when the get_file_data
 * parser is missing but ABSPATH points to a WordPress root. It only records
 * that the parser file has been required.
 */

declare(strict_types=1);

define('OMEGA_FIXTURE_PLUGIN_PARSER_LOADED', true);
