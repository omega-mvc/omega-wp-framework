<?php

/**
 * Part of Omega - Tests Admin Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Admin\Fixtures;

/**
 * Admin setup class counting how many times it has been instantiated.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class SampleAdminSetup
{
    /** Number of times the class has been instantiated during a test. */
    public static int $instances = 0;

    /**
     * Record the instantiation performed by the admin setup resolver.
     */
    public function __construct()
    {
        self::$instances++;
    }

    /**
     * Reset the recorded instantiations.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instances = 0;
    }
}
