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

use Omega\Admin\Features\AbstractFeatures;

/**
 * Concrete feature storing the application given to the abstract constructor.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class SampleFeature extends AbstractFeatures
{
    /** Number of times init() has been called during a test. */
    public static int $initCalls = 0;

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        self::$initCalls++;
    }

    /**
     * Reset the recorded init calls.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$initCalls = 0;
    }
}
