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

namespace Automattic\WooCommerce\Utilities;

/**
 * Minimal stand-in for the WooCommerce feature declaration utility.
 *
 * WooCommerce::registerFeatures() guards every declaration behind
 * class_exists(FeaturesUtil::class), so the positive branch is unreachable
 * unless the class is defined. The real class ships with WooCommerce and is
 * therefore never loaded by the test suite, which would leave the two
 * declaration lines uncovered.
 *
 * The class deliberately lives outside the Tests namespace because production
 * code references it by its own fully qualified name. It is never autoloaded:
 * Composer maps the Tests namespace only, so the class appears exactly when a
 * test requires this file. That keeps the "WooCommerce is not installed"
 * scenario reachable in the same suite.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class FeaturesUtil
{
    /**
     * Compatibility declarations recorded by the stub, in call order.
     *
     * @var list<array{feature: string, file: string, positive: bool}>
     */
    public static array $declarations = [];

    /**
     * Record a compatibility declaration instead of talking to WooCommerce.
     *
     * @param string $feature  Feature identifier, e.g. "custom_order_tables".
     * @param string $file     Absolute path of the plugin declaring the feature.
     * @param bool   $positive Whether the feature is declared as supported.
     * @return void
     */
    public static function declare_compatibility(string $feature, string $file, bool $positive = true): void
    {
        self::$declarations[] = [
            'feature'  => $feature,
            'file'     => $file,
            'positive' => $positive,
        ];
    }

    /**
     * Forget every recorded declaration.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$declarations = [];
    }
}
