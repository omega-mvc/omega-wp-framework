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

namespace Tests\Application\Support;

use Omega\Container\ServiceProvider;

/**
 * Fake service provider used to observe the registration and boot lifecycle.
 *
 * It records every invocation of register() and boot() on static counters,
 * and registers a trivial "fake.service" singleton so tests can resolve
 * the binding afterwards.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class FakeProvider extends ServiceProvider
{
    /**
     * Number of times register() has been invoked.
     */
    public static int $registerCalls = 0;

    /**
     * Number of times boot() has been invoked.
     */
    public static int $bootCalls = 0;

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        self::$registerCalls++;

        $this->app->singleton('fake.service', fn (): string => 'fake');
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        self::$bootCalls++;
    }

    /**
     * Reset the static counters between tests.
     */
    public static function reset(): void
    {
        self::$registerCalls = 0;
        self::$bootCalls = 0;
    }
}
