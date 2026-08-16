<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * Fixture helper loaded from within an application root directory to exercise
 * the ApplicationFactory backtrace-based application resolution: the include
 * file path is visible in the execution stack of app() and matches the root
 * directory of the resolver application.
 */

declare(strict_types=1);

use Omega\Application\ApplicationFactory;

return ApplicationFactory::app('config');
