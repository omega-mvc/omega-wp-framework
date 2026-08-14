<?php

/**
 * Part of Omega - Tests Str Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Str;

use Omega\Str\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Str utility class.
 *
 * @category  Tests
 * @package   Str
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Str::class)]
final class StrTest extends TestCase
{
    /**
     * Test a simple key is retrieved from the root level.
     */
    public function testGetNestedValueWithSimpleKey(): void
    {
        $data = ['name' => 'Omega'];

        $this->assertSame('Omega', Str::getNestedValue($data, 'name'));
    }

    /**
     * Test a deeply nested value is resolved with dot notation.
     */
    public function testGetNestedValueWithDotNotation(): void
    {
        $data = ['user' => ['profile' => ['email' => 'test@example.com']]];

        $this->assertSame('test@example.com', Str::getNestedValue($data, 'user.profile.email'));
    }

    /**
     * Test a missing key falls back to the default value.
     */
    public function testGetNestedValueReturnsDefaultForMissingKey(): void
    {
        $data = ['name' => 'Omega'];

        $this->assertSame('fallback', Str::getNestedValue($data, 'missing', 'fallback'));
        $this->assertNull(Str::getNestedValue($data, 'missing'));
    }

    /**
     * Test traversal stops with the default when a segment is not an array.
     */
    public function testGetNestedValueReturnsDefaultWhenTraversalHitsNonArray(): void
    {
        $data = ['user' => 'not-an-array'];

        $this->assertSame('fallback', Str::getNestedValue($data, 'user.email', 'fallback'));
    }

    /**
     * Test falsy values are returned as-is instead of the default.
     */
    public function testGetNestedValueReturnsFalsyValues(): void
    {
        $data = ['count' => 0, 'flag' => false, 'empty' => ''];

        $this->assertSame(0, Str::getNestedValue($data, 'count', 'fallback'));
        $this->assertFalse(Str::getNestedValue($data, 'flag', 'fallback'));
        $this->assertSame('', Str::getNestedValue($data, 'empty', 'fallback'));
    }

    /**
     * Test a stored null value with a simple key falls back to the default.
     *
     * The root-level branch uses the null coalescing operator, which treats a
     * stored null as missing.
     */
    public function testGetNestedValueWithNullValueForSimpleKey(): void
    {
        $data = ['value' => null];

        $this->assertSame('fallback', Str::getNestedValue($data, 'value', 'fallback'));
    }

    /**
     * Test a stored null value with a nested key is preserved.
     *
     * The dot-notation branch relies on array_key_exists(), so an explicit
     * null is returned instead of the default.
     */
    public function testGetNestedValueWithNullValueForNestedKey(): void
    {
        $data = ['user' => ['value' => null]];

        $this->assertNull(Str::getNestedValue($data, 'user.value', 'fallback'));
    }

    /**
     * Test a simple key is set at the root level.
     */
    public function testSetNestedValueWithSimpleKey(): void
    {
        $data = [];

        Str::setNestedValue($data, 'name', 'Omega');

        $this->assertSame(['name' => 'Omega'], $data);
    }

    /**
     * Test intermediate arrays are created automatically.
     */
    public function testSetNestedValueCreatesIntermediateArrays(): void
    {
        $data = [];

        Str::setNestedValue($data, 'user.profile.email', 'test@example.com');

        $this->assertSame(['user' => ['profile' => ['email' => 'test@example.com']]], $data);
    }

    /**
     * Test a scalar segment is replaced by a nested array.
     */
    public function testSetNestedValueOverwritesScalarWithArray(): void
    {
        $data = ['user' => 'scalar'];

        Str::setNestedValue($data, 'user.name', 'John');

        $this->assertSame(['user' => ['name' => 'John']], $data);
    }

    /**
     * Test an existing value is overwritten.
     */
    public function testSetNestedValueOverwritesExistingValue(): void
    {
        $data = ['name' => 'old'];

        Str::setNestedValue($data, 'name', 'new');

        $this->assertSame(['name' => 'new'], $data);
    }

    /**
     * Test sibling keys are preserved when writing a new nested value.
     */
    public function testSetNestedValuePreservesSiblingKeys(): void
    {
        $data = ['user' => ['email' => 'test@example.com']];

        Str::setNestedValue($data, 'user.name', 'John');

        $this->assertSame(['user' => ['email' => 'test@example.com', 'name' => 'John']], $data);
    }

    /**
     * Test hyphens are converted to underscores.
     */
    public function testToSnakeConvertsHyphens(): void
    {
        $this->assertSame('user_name', Str::toSnake('user-name'));
        $this->assertSame('omega_wp_framework', Str::toSnake('omega-wp-framework'));
    }

    /**
     * Test an already snake-cased string is returned unchanged.
     */
    public function testToSnakeLeavesAlreadySnakeCase(): void
    {
        $this->assertSame('user_name', Str::toSnake('user_name'));
    }

    /**
     * Test the empty string is handled.
     */
    public function testToSnakeHandlesEmptyString(): void
    {
        $this->assertSame('', Str::toSnake(''));
    }

    /**
     * Test a matching prefix is detected.
     */
    public function testStartsWithReturnsTrueForMatchingPrefix(): void
    {
        $this->assertTrue(Str::startsWith('OmegaFramework', 'Omega'));
        $this->assertTrue(Str::startsWith('exact', 'exact'));
    }

    /**
     * Test a non-matching prefix is rejected.
     */
    public function testStartsWithReturnsFalseForNonMatchingPrefix(): void
    {
        $this->assertFalse(Str::startsWith('OmegaFramework', 'omega'));
        $this->assertFalse(Str::startsWith('OmegaFramework', 'Framework'));
    }

    /**
     * Test an empty needle always matches.
     */
    public function testStartsWithWithEmptyNeedle(): void
    {
        $this->assertTrue(Str::startsWith('anything', ''));
    }
}
