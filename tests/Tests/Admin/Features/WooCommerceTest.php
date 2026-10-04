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

namespace Tests\Admin\Features;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Omega\Admin\Features\AbstractFeatures;
use Omega\Admin\Features\WooCommerce;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionProperty;
use Tests\Admin\AdminTestCase;
use Tests\Routing\WordPressRuntime;

use function array_filter;
use function array_key_last;
use function array_values;

/**
 * Test the WooCommerce admin feature integration.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(WooCommerce::class)]
final class WooCommerceTest extends AdminTestCase
{
    /**
     * Return the number of hooks registered for the given hook name.
     *
     * @param string $hookName Hook name to count
     * @return int The number of registered hooks
     */
    private function countActions(string $hookName): int
    {
        return count(array_values(array_filter(
            WordPressRuntime::$actions,
            static fn(array $action): bool => $action[0] === $hookName
        )));
    }

    /**
     * Return the callback registered last for the given hook name.
     *
     * @param string $hookName Hook name to look up
     * @return mixed The recorded hook callback
     */
    private function actionCallback(string $hookName): mixed
    {
        $recorded = array_values(array_filter(
            WordPressRuntime::$actions,
            static fn(array $action): bool => $action[0] === $hookName
        ));

        if ($recorded === []) {
            $this->fail(sprintf('No "%s" hook has been registered.', $hookName));
        }

        return $recorded[array_key_last($recorded)][1] ?? null;
    }

    /**
     * Test the compatibility hook is registered when the feature is enabled.
     *
     * @return void
     */
    public function testItRegistersTheCompatibilityHookWhenEnabled(): void
    {
        $this->bindConfig(['features' => ['wc' => ['compatibility' => true]]]);

        $feature = new WooCommerce($this->app);
        $feature->init();

        $this->assertSame(1, $this->countActions('before_woocommerce_init'));
        $this->assertSame([$feature, 'registerFeatures'], $this->actionCallback('before_woocommerce_init'));
    }

    /**
     * Test no hook is registered when the compatibility feature is disabled.
     *
     * @param array<string, mixed> $config Configuration repository content
     * @return void
     */
    #[DataProvider('disabledCompatibilityProvider')]
    public function testItRegistersNoHookWhenTheFeatureIsDisabled(array $config): void
    {
        $this->bindConfig($config);

        $feature = new WooCommerce($this->app);
        $feature->init();

        $this->assertSame(0, $this->countActions('before_woocommerce_init'));
    }

    /**
     * Configuration values that keep the compatibility feature disabled.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function disabledCompatibilityProvider(): array
    {
        return [
            'explicit false' => [['features' => ['wc' => ['compatibility' => false]]]],
            'missing key'    => [['features' => ['wc' => []]]],
            'empty config'   => [[]],
        ];
    }

    /**
     * Test compatibility is skipped while WooCommerce utilities are missing.
     *
     * The feature utilities class belongs to WooCommerce, so the declaration
     * must be skipped instead of failing when the plugin is not installed.
     *
     * This test deliberately runs in the shared process, where the FeaturesUtil
     * stub is never required, so class_exists() keeps reporting false here.
     * Its counterpart below loads the stub in a separate process instead.
     *
     * @return void
     */
    public function testItSkipsTheDeclarationWhenTheUtilitiesAreMissing(): void
    {
        $this->bindConfig(['features' => ['wc' => ['compatibility' => true]]]);

        $feature = new WooCommerce($this->app);
        $feature->init();

        $this->assertFalse(class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil'));
        $feature->registerFeatures();

        $this->assertSame(1, $this->countActions('before_woocommerce_init'));
    }

    /**
     * Test both compatibility features are declared when the utilities exist.
     *
     * A PHP class cannot be undefined once declared, so requiring the stub in
     * the shared process would make the scenario above unreachable for good.
     * The stub is therefore loaded here, in a dedicated process, and the
     * coverage collected by the child is merged back by PHPUnit.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    public function testItDeclaresBothCompatibilityFeaturesWhenTheUtilitiesExist(): void
    {
        require_once __DIR__ . '/Fixtures/FeaturesUtil.php';

        $this->assertTrue(class_exists(FeaturesUtil::class));

        FeaturesUtil::reset();

        $feature = new WooCommerce($this->app);
        $feature->registerFeatures();

        $appFile = $this->app->getAppFile();

        $this->assertSame([
            ['feature' => 'custom_order_tables', 'file' => $appFile, 'positive' => true],
            ['feature' => 'product_block_editor', 'file' => $appFile, 'positive' => true],
        ], FeaturesUtil::$declarations);
    }

    /**
     * Test the feature is created with the application under test.
     *
     * @return void
     */
    public function testItKeepsTheApplicationGivenToTheConstructor(): void
    {
        $feature  = new WooCommerce($this->app);
        $property = new ReflectionProperty(AbstractFeatures::class, 'app');

        $this->assertSame($this->app, $property->getValue($feature));
    }
}
