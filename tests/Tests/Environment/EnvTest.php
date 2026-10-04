<?php

/**
 * Part of Omega - Tests\Support Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

/** @noinspection PhpExpressionResultUnusedInspection */

declare(strict_types=1);

namespace Tests\Environment;

use Omega\Environment\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\FixturesPathTrait;

/**
 * Tests the Env class behavior.
 *
 * Ensures correct retrieval of environment variables from loaded
 * values or system fallback, proper default handling, and accurate
 * type casting of string representations.
 *
 * @category  Tests
 * @package   Support
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */
#[CoversClass(Env::class)]
final class EnvTest extends TestCase
{
    use FixturesPathTrait;

    private string $fixturePath;

    /**
     * @var string[] Keys loaded from the .env.test fixture.
     */
    private const array FIXTURE_KEYS = ['APP_NAME', 'APP_DEBUG', 'APP_TIMEOUT', 'APP_EMPTY', 'APP_NULL'];

    protected function setUp(): void
    {
        $this->fixturePath = $this->setFixturePath('/fixtures/environment/');
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Env::class);
        $valuesProp = $reflection->getProperty('values');
        $valuesProp->setValue(null, []);

        foreach (self::FIXTURE_KEYS as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    /**
     * Test it can create immutable Dotenv instance and load values.
     *
     * @return void
     */
    public function testItCanCreateImmutable(): void
    {
        Env::load($this->fixturePath, '.env.test');

        $this->assertSame('Omega', Env::get('APP_NAME'));
    }

    /**
     * Test loading the same file twice preserves the previously loaded values.
     *
     * Regression test: Dotenv's immutable loader only returns the variables
     * that were not already defined in the process environment, so a second
     * load used to replace the whole store with an empty/partial array.
     */
    public function testItPreservesValuesWhenLoadedTwice(): void
    {
        Env::load($this->fixturePath, '.env.test');
        Env::load($this->fixturePath, '.env.test');

        $this->assertSame('Omega', Env::get('APP_NAME'));
        $this->assertTrue(Env::get('APP_DEBUG'));
        $this->assertSame(42, Env::get('APP_TIMEOUT'));
        $this->assertSame('', Env::get('APP_EMPTY'));
        $this->assertNull(Env::get('APP_NULL'));
    }

    /**
     * Test it returns default value when key not found.
     *
     * @return void
     */
    public function testItReturnsDefaultValue(): void
    {
        $default = 'default_value';
        $this->assertSame($default, Env::get('NON_EXISTING_KEY', $default));
    }

    /**
     * Test the default is returned verbatim, without any type conversion.
     *
     * A default is what the caller wants when the key is in no source, so a
     * default reading "null" has to stay the string "null" and stay
     * distinguishable from a key that resolves to a real null.
     *
     * @param mixed $default The default handed to the accessor.
     * @return void
     */
    #[DataProvider('defaultValueProvider')]
    public function testItReturnsTheDefaultWithoutCastingIt(mixed $default): void
    {
        $this->assertSame($default, Env::get('NON_EXISTING_KEY', $default));
    }

    /** @return array<string, array<int, mixed>> */
    public static function defaultValueProvider(): array
    {
        return [
            'null as a string'   => ['null'],
            'empty as a string'  => ['empty'],
            'true as a string'   => ['true'],
            'false as a string'  => ['false'],
            'zero as a string'   => ['007'],
            'numeric as string'  => ['42'],
            'typo as a string'   => ['flase'],
            'plain as a string'  => ['localhost'],
            'real null'          => [null],
            'real false'         => [false],
            'real int'           => [42],
            'real float'         => [3.14],
            'real empty array'   => [[]],
        ];
    }

    /**
     * Test a value read from the process environment is still converted.
     *
     * The default is the only thing that must stay verbatim: values that do
     * come from a source keep the documented conversions.
     *
     * @return void
     */
    public function testItStillCastsValuesComingFromTheSystemEnvironment(): void
    {
        putenv('SYSTEM_BOOL=false');
        putenv('SYSTEM_NULL=null');
        putenv('SYSTEM_NUMBER=42');

        $this->assertFalse(Env::get('SYSTEM_BOOL'));
        $this->assertNull(Env::get('SYSTEM_NULL'));
        $this->assertSame(42, Env::get('SYSTEM_NUMBER'));

        putenv('SYSTEM_BOOL');
        putenv('SYSTEM_NULL');
        putenv('SYSTEM_NUMBER');
    }

    /**
     * Test a stored value is converted even when it reads like a default.
     *
     * The two cases must stay apart: a file holding the string "null" is a
     * configuration value and resolves to null, while a caller passing "null"
     * as a default gets the string back.
     *
     * @return void
     */
    public function testItDistinguishesAStoredValueFromAReadDefault(): void
    {
        $reflection = new ReflectionClass(Env::class);
        $valuesProp = $reflection->getProperty('values');
        $valuesProp->setValue(null, ['STORED_NULL' => 'null']);

        $this->assertNull(Env::get('STORED_NULL'));
        $this->assertSame('null', Env::get('STORED_MISSING', 'null'));
    }

    /**
     * Test string conversion rules for boolean, null, empty, numeric values.
     *
     * @param string $key
     * @param mixed $rawValue
     * @param mixed $expected
     * @return void
     */
    #[DataProvider('stringConversionProvider')]
    public function testStringConversions(string $key, mixed $rawValue, mixed $expected): void
    {
        $reflection = new ReflectionClass(Env::class);
        $valuesProp = $reflection->getProperty('values');
        $valuesProp->setValue(null, [$key => $rawValue]);

        $this->assertSame($expected, Env::get($key));
    }

    /** @return array<int, array<int, mixed>> */
    public static function stringConversionProvider(): array
    {
        return [
            ['BOOL_TRUE', 'true', true],
            ['BOOL_FALSE', 'false', false],
            ['NULL_VAL', 'null', null],
            ['EMPTY_VAL', 'empty', ''],
            ['NUMERIC_INT', '42', 42],
            ['NUMERIC_FLOAT', '3.14', 3.14],
            ['NORMAL_STRING', 'Omega', 'Omega'],
            ['STRING_ALPHA', 'alpha', 'alpha'],
            ['STRING_ZERO', '0', 0],
            ['STRING_FLOAT_STRANGE', '10.50', 10.5],
            ['STRING_EMPTY_SPACE', ' ', ' '],
        ];
    }

    /**
     * Test that values not string are returned as is.
     *
     * @return void
     */
    public function testNonStringValues(): void
    {
        $reflection = new ReflectionClass(Env::class);
        $valuesProp = $reflection->getProperty('values');
        $valuesProp->setValue(null, [
            'ARRAY_VAL'  => [1, 2, 3],
            'INT_VAL'    => 100,
            'BOOL_VAL'   => false,
            'NULL_VAL'   => null,
        ]);

        $this->assertSame([1, 2, 3], Env::get('ARRAY_VAL'));
        $this->assertSame(100, Env::get('INT_VAL'));
        $this->assertFalse(Env::get('BOOL_VAL'));
        $this->assertNull(Env::get('NULL_VAL'));
    }

    /**
     * Test it fall back to get env.
     *
     * @return void
     */
    public function testItFallsBackToGetenv(): void
    {
        putenv('SYSTEM_VAR=hello');
        $this->assertSame('hello', Env::get('SYSTEM_VAR'));
        putenv('SYSTEM_VAR');
    }
}
