<?php

/**
 * Part of Omega - Tests\Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database;

use Omega\Database\ORM\Casts\ArrayCast;
use Omega\Database\ORM\Casts\Attribute;
use Omega\Database\ORM\Casts\BooleanCast;
use Omega\Database\ORM\Casts\MoneyCast;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Database\Fixtures\SoftDeletePost;

/**
 * Covers the attribute casters of the ORM.
 *
 * @category  Tests
 * @package   Database
 * @subpackage ORM
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ArrayCast::class)]
#[CoversClass(Attribute::class)]
#[CoversClass(BooleanCast::class)]
#[CoversClass(MoneyCast::class)]
final class CastsTest extends DatabaseTestCase
{
    public function testArrayCastDecodesAScalarValue(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new ArrayCast();

        $this->assertSame(['tag' => 'php'], $cast->get($model, 'meta', '{"tag":"php"}', []));
    }

    public function testArrayCastDecodesNonScalarValuesAsAnEmptyString(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new ArrayCast();

        $this->assertNull($cast->get($model, 'meta', ['tag' => 'php'], []));
    }

    public function testArrayCastEncodesTheValueAsJson(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new ArrayCast();

        $this->assertSame('{"tag":"php"}', $cast->set($model, 'meta', ['tag' => 'php'], []));
    }

    public function testBooleanCastNormalisesTruthyAndFalsyValues(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new BooleanCast();

        $this->assertTrue($cast->get($model, 'published', '1', []));
        $this->assertFalse($cast->get($model, 'published', 0, []));
    }

    public function testBooleanCastStoresScalarValuesAsIntegers(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new BooleanCast();

        $this->assertSame(1, $cast->set($model, 'published', '1', []));
    }

    public function testBooleanCastStoresNonScalarValuesAsZero(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new BooleanCast();

        $this->assertSame(0, $cast->set($model, 'published', ['1'], []));
    }

    public function testMoneyCastExposesTheStoredAmountAsAFloat(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new MoneyCast();

        $this->assertSame(12.34, $cast->get($model, 'price', '1234', []));
    }

    public function testMoneyCastFallsBackToZeroForNonNumericValues(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new MoneyCast();

        $this->assertSame(0.0, $cast->get($model, 'price', 'free', []));
    }

    public function testMoneyCastStoresTheAmountAsAnInteger(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new MoneyCast();

        $this->assertSame(1234, $cast->set($model, 'price', 12.34, []));
    }

    public function testMoneyCastStoresNonNumericValuesAsZero(): void
    {
        $model = new SoftDeletePost(['id' => 1]);
        $cast  = new MoneyCast();

        $this->assertSame(0, $cast->set($model, 'price', 'free', []));
    }

    public function testAttributeAppliesTheDefaultCachingConfiguration(): void
    {
        $attribute = new Attribute();

        $this->assertNull($attribute->get);
        $this->assertNull($attribute->set);
        $this->assertFalse($attribute->withCaching);
        $this->assertTrue($attribute->withObjectCaching);
    }

    public function testAttributeMakeStoresTheGivenCallbacks(): void
    {
        $get = static fn (): string => 'value';
        $set = static fn (mixed $value): mixed => $value;

        $attribute = Attribute::make($get, $set);

        $this->assertSame($get, $attribute->get);
        $this->assertSame($set, $attribute->set);
    }

    public function testAttributeGetOnlyRegistersAnAccessor(): void
    {
        $get = static fn (): string => 'value';

        $attribute = Attribute::get($get);

        $this->assertSame($get, $attribute->get);
        $this->assertNull($attribute->set);
    }

    public function testAttributeSetOnlyRegistersAMutator(): void
    {
        $set = static fn (mixed $value): mixed => $value;

        $attribute = Attribute::set($set);

        $this->assertNull($attribute->get);
        $this->assertSame($set, $attribute->set);
    }

    public function testAttributeFluentConfigurationMethodsAreChainable(): void
    {
        $attribute = new Attribute();

        $this->assertSame($attribute, $attribute->withoutObjectCaching());
        $this->assertFalse($attribute->withObjectCaching);

        $this->assertSame($attribute, $attribute->shouldCache());
        $this->assertTrue($attribute->withCaching);
    }
}
