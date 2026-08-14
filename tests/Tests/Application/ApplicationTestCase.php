<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Application;

use PHPUnit\Framework\TestCase;
use Tests\Application\Support\FakeProvider;
use Tests\FixturesPathTrait;
use Tests\Routing\WordPressRuntime;

/**
 * Base test case for the Application package.
 *
 * Resets the shared runtime registries between tests and exposes the
 * fixture base paths used to build application instances.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
abstract class ApplicationTestCase extends TestCase
{
    use FixturesPathTrait;

    /**
     * Reset the provider counters and the WordPress runtime registries.
     */
    protected function setUp(): void
    {
        parent::setUp();

        FakeProvider::reset();
        WordPressRuntime::reset();
    }

    /**
     * Base path of the plugin fixture, including its entry file.
     *
     * @return string Absolute plugin fixture path
     */
    protected function pluginBasePath(): string
    {
        return $this->setFixturePath('/fixtures/app/plugin/sample');
    }

    /**
     * Base path of the theme fixture.
     *
     * @return string Absolute theme fixture path
     */
    protected function themeBasePath(): string
    {
        return $this->setFixturePath('/fixtures/app/theme');
    }

    /**
     * Base path of an application without any configuration directory.
     *
     * @return string Absolute fixture root path
     */
    protected function emptyBasePath(): string
    {
        return $this->setFixturePath('/fixtures/app');
    }
}
