<?php

/**
 * Part of Omega - Tests Config Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Config;

use Omega\Config\ConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ConfigRepository retrieval and casting behaviour.
 *
 * @category  Tests
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryTest extends TestCase
{
    /**
     * Test a leaf value is resolved through dot notation.
     */
    public function testGetsLeafValueWithDotNotation(): void
    {
        $this->assertSame('local', $this->makeRepository()->get('app.environment'));
    }

    /**
     * Test a deeply nested leaf value is resolved.
     */
    public function testGetsDeeplyNestedLeafValue(): void
    {
        $this->assertSame('localhost', $this->makeRepository()->get('database.connections.mysql.host'));
    }

    /**
     * Test the default is returned for a missing key.
     */
    public function testReturnsDefaultForMissingKey(): void
    {
        $repository = $this->makeRepository();

        $this->assertNull($repository->get('app.missing'));
        $this->assertSame('fallback', $repository->get('app.missing', 'fallback'));
    }

    /**
     * Test a parent key resolves to its nested array.
     */
    public function testReturnsNestedArrayForParentKey(): void
    {
        $this->assertSame(
            ['host' => 'localhost', 'port' => '3306'],
            $this->makeRepository()->get('database.connections.mysql')
        );
    }

    /**
     * Test the default is returned when traversal stops at a missing segment.
     */
    public function testReturnsDefaultForDeeplyMissingKey(): void
    {
        $this->assertNull($this->makeRepository()->get('database.connections.redis.host'));
    }

    /**
     * Test the default is returned when traversal crosses a scalar value.
     */
    public function testReturnsDefaultWhenTraversingThroughScalar(): void
    {
        $this->assertNull($this->makeRepository()->get('app.name.missing'));
    }

    /**
     * Test underscore-separated keys do not resolve to dot-notated values.
     *
     * Lookup is exact tree traversal: a dotted path only ever resolves the
     * key actually stored under those segments, never underscore variants.
     */
    public function testDoesNotResolveUnderscoreSeparatedKeys(): void
    {
        $repository = $this->makeRepository();

        $this->assertNull($repository->get('app_environment'));
        $this->assertNull($repository->get('database_connections_mysql_host'));
    }

    /**
     * Test an underscore config key is reachable only through its exact name.
     */
    public function testKeepsUnderscoreConfigKeysExact(): void
    {
        $repository = $this->makeRepository();

        $this->assertSame('legacy', $repository->get('legacy_key'));
        $this->assertNull($repository->get('legacy.key'));
    }

    /**
     * Test the default is returned when a stored value is null.
     */
    public function testReturnsDefaultForNullValue(): void
    {
        $this->assertSame('fallback', $this->makeRepository()->get('app.empty', 'fallback'));
    }

    /**
     * Test has() detects existing keys.
     */
    public function testHasDetectsExistingKeys(): void
    {
        $repository = $this->makeRepository();

        $this->assertTrue($repository->has('app.environment'));
        $this->assertTrue($repository->has('database'));
        $this->assertFalse($repository->has('app.missing'));
    }

    /**
     * Test has() reports false for a null-valued key.
     *
     * Index resolution uses isset(), so stored null values are treated
     * as missing. This documents the current quirk.
     */
    public function testHasReturnsFalseForNullValue(): void
    {
        $this->assertFalse($this->makeRepository()->has('app.empty'));
    }

    /**
     * Test string() sanitizes the resolved value.
     */
    public function testStringSanitizesValue(): void
    {
        $this->assertSame('Sample', $this->makeRepository()->string('app.name'));
    }

    /**
     * Test string() returns the default when the key is missing.
     */
    public function testStringReturnsDefault(): void
    {
        $this->assertSame('fallback', $this->makeRepository()->string('app.missing', 'fallback'));
    }

    /**
     * Test boolean() accepts the truthy string variants.
     */
    public function testBooleanTruthyVariants(): void
    {
        $repository = $this->makeRepository();

        $this->assertTrue($repository->boolean('app.debug'));
        $this->assertTrue($repository->boolean('features.enabled'));
        $this->assertTrue($repository->boolean('features.on_switch'));
    }

    /**
     * Test boolean() accepts the falsy string variants.
     */
    public function testBooleanFalsyVariants(): void
    {
        $this->assertFalse($this->makeRepository()->boolean('features.cache'));
    }

    /**
     * Test boolean() casts numeric strings.
     */
    public function testBooleanNumericCast(): void
    {
        $repository = $this->makeRepository();

        $this->assertTrue($repository->boolean('database.connections.mysql.port'));
        $this->assertFalse($repository->boolean('features.count'));
    }

    /**
     * Test boolean() accepts every truthy string variant.
     */
    public function testBooleanAcceptsEveryTruthyLiteral(): void
    {
        $repository = new ConfigRepository(['a' => '1', 'b' => 'true', 'c' => 'yes', 'd' => 'on']);

        $this->assertTrue($repository->boolean('a'));
        $this->assertTrue($repository->boolean('b'));
        $this->assertTrue($repository->boolean('c'));
        $this->assertTrue($repository->boolean('d'));
    }

    /**
     * Test boolean() accepts every falsy string variant.
     */
    public function testBooleanAcceptsEveryFalsyLiteral(): void
    {
        $repository = new ConfigRepository(['a' => '0', 'b' => 'false', 'c' => 'no', 'd' => 'off']);

        $this->assertFalse($repository->boolean('a'));
        $this->assertFalse($repository->boolean('b'));
        $this->assertFalse($repository->boolean('c'));
        $this->assertFalse($repository->boolean('d'));
    }

    /**
     * Test string() returns empty when the key is missing and no default is given.
     */
    public function testStringReturnsEmptyWhenNoDefault(): void
    {
        $this->assertSame('', $this->makeRepository()->string('app.missing'));
    }

    /**
     * Test string() converts non-string scalar values instead of discarding them.
     */
    public function testStringConvertsScalarValue(): void
    {
        $repository = new ConfigRepository(['retries' => 3, 'pi' => 3.14]);

        $this->assertSame('3', $repository->string('retries'));
        $this->assertSame('3.14', $repository->string('pi'));
    }

    /**
     * Test string() falls back to the default for array values.
     */
    public function testStringReturnsDefaultForArrayValue(): void
    {
        $repository = $this->makeRepository();

        $this->assertSame('fallback', $repository->string('database.connections.mysql', 'fallback'));
        $this->assertSame('', $repository->string('database.connections.mysql'));
    }

    /**
     * Test lookup never falls through to a key with the other separator.
     */
    public function testGetRespectsExactKeySeparator(): void
    {
        $repository = new ConfigRepository(['a_b' => 'PIPPO']);

        $this->assertNull($repository->get('a.b'));
        $this->assertFalse($repository->has('a.b'));
        $this->assertSame('fallback', $repository->get('a.b', 'fallback'));
        $this->assertSame('PIPPO', $repository->get('a_b'));

        // A stored key containing a literal dot is not a navigable path.
        $reverse = new ConfigRepository(['a.b' => 'x']);

        $this->assertNull($reverse->get('a_b'));
        $this->assertNull($reverse->get('a.b'));
        $this->assertSame('x', $reverse->getAll()['a.b']);
    }

    /**
     * Test boolean() falls back to the default for unknown values.
     */
    public function testBooleanUnknownFallsBackToDefault(): void
    {
        $repository = $this->makeRepository();

        $this->assertTrue($repository->boolean('features.mode', true));
        $this->assertFalse($repository->boolean('features.mode', false));
        $this->assertFalse($repository->boolean('features.mode'));
        $this->assertFalse($repository->boolean('app.missing'));
        $this->assertTrue($repository->boolean('app.missing', true));
    }

    /**
     * Test boolean() returns false when the key is missing and no default is given.
     */
    public function testBooleanReturnsFalseWhenNoDefault(): void
    {
        $this->assertFalse($this->makeRepository()->boolean('app.missing'));
    }

    /**
     * Test boolean() returns the default for a non-string, non-null,
     * non-bool, non-numeric value (e.g. array).
     */
    public function testBooleanReturnsDefaultForArrayValue(): void
    {
        $this->assertFalse($this->makeRepository()->boolean('database.connections.mysql'));
    }

    /**
     * Test boolean() returns a non-null default for a non-string value.
     */
    public function testBooleanReturnsNonNullDefaultForArrayValue(): void
    {
        $this->assertTrue($this->makeRepository()->boolean('database.connections.mysql', true));
    }

    /**
     * Test the default is returned when the resolved value matches it.
     */
    public function testReturnsDefaultWhenResolvedValueMatchesDefault(): void
    {
        $default = ['host' => 'localhost', 'port' => '3306'];

        $this->assertSame($default, $this->makeRepository()->get('database.connections.mysql', $default));
    }

    /**
     * Test integer() casts the resolved value.
     */
    public function testIntegerCasts(): void
    {
        $repository = $this->makeRepository();

        $this->assertSame(3306, $repository->integer('database.connections.mysql.port'));
        $this->assertSame(0, $repository->integer('app.missing'));
        $this->assertSame(7, $repository->integer('app.missing', 7));
    }

    /**
     * Test integer() returns zero when the key is missing and no default is given.
     */
    public function testIntegerReturnsZeroWhenNoDefault(): void
    {
        $this->assertSame(0, $this->makeRepository()->integer('app.missing'));
    }

    /**
     * Test integer() casts a float config value to int.
     */
    public function testIntegerCastsFloatToInt(): void
    {
        $repository = new ConfigRepository(['pi' => 3.14]);

        $this->assertSame(3, $repository->integer('pi'));
    }

    /**
     * Test integer() returns the non-null default when the resolved value
     * is not int, float, or string (e.g. null).
     */
    public function testIntegerReturnsNonNullDefaultForNullValue(): void
    {
        $this->assertSame(5, $this->makeRepository()->integer('app.empty', 5));
    }

    /**
     * Test getAll() returns the full configuration array.
     */
    public function testGetAllReturnsFullConfig(): void
    {
        $this->assertSame($this->fixtureConfig(), $this->makeRepository()->getAll());
    }

    /**
     * Build a repository backed by a shared fixture configuration.
     *
     * @return ConfigRepository Repository instance
     */
    private function makeRepository(): ConfigRepository
    {
        return new ConfigRepository($this->fixtureConfig());
    }

    /**
     * The configuration dataset used by the tests.
     *
     * @return array<string, mixed> Fixture configuration
     */
    private function fixtureConfig(): array
    {
        return [
            'app' => [
                'environment' => 'local',
                'debug'       => true,
                'name'        => '<b>Sample</b>',
                'empty'       => null,
            ],
            'database' => [
                'connections' => [
                    'mysql' => [
                        'host' => 'localhost',
                        'port' => '3306',
                    ],
                ],
            ],
            'features' => [
                'cache'     => 'off',
                'enabled'   => 'yes',
                'on_switch' => 'on',
                'mode'      => 'auto',
                'count'     => '0',
                'retries'   => '3',
            ],
            'legacy_key' => 'legacy',
        ];
    }
}
