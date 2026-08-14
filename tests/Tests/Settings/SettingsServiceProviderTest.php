<?php

/**
 * Part of Omega - Tests Settings Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Settings;

use Omega\Settings\SettingsRepository;
use Omega\Settings\SettingsServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the SettingsServiceProvider binding.
 *
 * @category  Tests
 * @package   Settings
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(SettingsServiceProvider::class)]
final class SettingsServiceProviderTest extends SettingsTestCase
{
    /**
     * Test the settings service resolves to a SettingsRepository.
     */
    public function testBindsSettingsRepository(): void
    {
        $this->assertInstanceOf(SettingsRepository::class, $this->makeApplication()->resolve('settings'));
    }

    /**
     * Test the settings service is registered as a singleton.
     */
    public function testSettingsServiceIsSingleton(): void
    {
        $application = $this->makeApplication();

        $this->assertSame($application->resolve('settings'), $application->resolve('settings'));
    }
}
