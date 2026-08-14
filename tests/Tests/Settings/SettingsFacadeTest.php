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

use Omega\Settings\Facade\Settings as SettingsFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Routing\WordPressRuntime;

/**
 * Tests the Settings facade proxy.
 *
 * @category  Tests
 * @package   Settings
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(SettingsFacade::class)]
final class SettingsFacadeTest extends SettingsTestCase
{
    /**
     * Test the facade exposes the settings accessor.
     */
    public function testReturnsFacadeAccessor(): void
    {
        $this->assertSame('settings', SettingsFacade::getFacadeAccessor());
    }

    /**
     * Test static calls are proxied to the container service.
     */
    public function testProxiesUpdateCallToContainerService(): void
    {
        $this->assertTrue(SettingsFacade::update('theme', 'dark'));
        $this->assertSame('dark', SettingsFacade::get('theme'));

        $this->assertSame(['theme' => 'dark'], WordPressRuntime::$options['plugin_settings']);
    }
}
