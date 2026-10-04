<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * Fixture helper loaded from within an application root directory to exercise
 * the facade application scoping: the include file path is visible in the
 * execution stack of AbstractFacade::getFacadeRoot() and matches the root
 * directory of the resolver application, so the same accessor must resolve
 * against a different container than the one used by the caller.
 */

declare(strict_types=1);

use Omega\Config\Facades\Config as ConfigFacade;

return ConfigFacade::get('app.environment');
