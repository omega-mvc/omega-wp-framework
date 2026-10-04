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

use Omega\Admin\Features\AbstractFeatures;
use Omega\Admin\Features\FeaturesInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use ReflectionProperty;
use Tests\Admin\AdminTestCase;
use Tests\Admin\Fixtures\SampleFeature;

/**
 * Test the AbstractFeatures contract.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractFeatures::class)]
final class AbstractFeaturesTest extends AdminTestCase
{
    /**
     * Test a feature implements the features contract.
     *
     * @return void
     */
    public function testAFeatureImplementsTheFeaturesContract(): void
    {
        $feature = new SampleFeature($this->app);

        $this->assertInstanceOf(FeaturesInterface::class, $feature);
        $this->assertInstanceOf(AbstractFeatures::class, $feature);
    }

    /**
     * Test the feature exposes the application given to the constructor.
     *
     * @return void
     */
    public function testItKeepsTheApplicationGivenToTheConstructor(): void
    {
        $feature  = new SampleFeature($this->app);
        $property = new ReflectionProperty(AbstractFeatures::class, 'app');

        $this->assertSame($this->app, $property->getValue($feature));
    }

    /**
     * Test the init method stays abstract on the base class.
     *
     * @return void
     */
    public function testTheInitMethodStaysAbstract(): void
    {
        $method = (new ReflectionClass(AbstractFeatures::class))->getMethod('init');

        $this->assertTrue($method->isAbstract());
        $this->assertSame('void', (string) $method->getReturnType());
    }

    /**
     * Test the base class cannot be instantiated on its own.
     *
     * @return void
     */
    public function testTheBaseClassIsAbstract(): void
    {
        $this->assertTrue((new ReflectionClass(AbstractFeatures::class))->isAbstract());
        $this->assertTrue((new ReflectionClass(FeaturesInterface::class))->isInterface());
    }
}
