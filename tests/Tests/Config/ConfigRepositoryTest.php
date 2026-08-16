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
     * Test underscore-separated keys resolve to dot-notated values.
     */
    public function testAcceptsUnderscoreSeparatedKeys(): void
    {
        $repository = $this->makeRepository();

        $this->assertSame('local', $repository->get('app_environment'));
        $this->assertSame('localhost', $repository->get('database_connections_mysql_host'));
    }

    /**
     * Test underscore keys in the config are reachable through dot notation.
     */
    public function testResolvesUnderscoreConfigKeysViaDot(): void
    {
        $repository = $this->makeRepository();

        $this->assertSame('legacy', $repository->get('legacy_key'));
        $this->assertSame('legacy', $repository->get('legacy.key'));
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
