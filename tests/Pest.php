<?php

/**
 * Part of Omega - Tests Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

use Tests\Application\ApplicationTestCase;
use Tests\Config\ConfigTestCase;
use Tests\Database\DatabaseTestCase;
use Tests\Facade\FacadeTestCase;
use Tests\Http\HttpTestCase;
use Tests\Routing\RoutingTestCase;
use Tests\Settings\SettingsTestCase;

/*
|--------------------------------------------------------------------------
| Test Cases
|--------------------------------------------------------------------------
|
| Binds each package base test case to the functional tests declared in its
| own directory, so a Pest test shares the fixture wiring, the setUp() and
| the tearDown() of the class based tests living next to it.
|
*/

pest()->extend(ApplicationTestCase::class)->in('Tests/Application');
pest()->extend(ConfigTestCase::class)->in('Tests/Config');
pest()->extend(DatabaseTestCase::class)->in('Tests/Database');
pest()->extend(FacadeTestCase::class)->in('Tests/Facade');
pest()->extend(HttpTestCase::class)->in('Tests/Http');
pest()->extend(RoutingTestCase::class)->in('Tests/Routing');
pest()->extend(SettingsTestCase::class)->in('Tests/Settings');
