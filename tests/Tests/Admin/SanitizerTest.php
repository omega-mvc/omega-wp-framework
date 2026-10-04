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

namespace Tests\Admin;

use Omega\Admin\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test the Sanitizer utility class.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class SanitizerTest extends AdminTestCase
{
    /**
     * Test the class exposes every documented sanitizer method.
     *
     * @return void
     */
    public function testItExposesEverySanitizerMethod(): void
    {
        $methods = [
            'boolean',
            'string',
            'textarea',
            'integer',
            'float',
            'email',
            'url',
            'arrayOfStrings',
            'cast',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(Sanitizer::class, $method),
                sprintf('Sanitizer::%s() is expected to exist.', $method)
            );
        }
    }

    /**
     * Test truthy and falsy values normalization.
     *
     * @param mixed $value
     * @param bool  $expected
     * @return void
     */
    #[DataProvider('booleanProvider')]
    public function testItSanitizesBooleans(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, Sanitizer::boolean($value));
    }

    /** @return array<string, array{0: mixed, 1: bool}> */
    public static function booleanProvider(): array
    {
        return [
            'native true'    => [true, true],
            'native false'   => [false, false],
            'integer one'    => [1, true],
            'integer zero'   => [0, false],
            'string true'    => ['true', true],
            'string false'   => ['false', false],
            'string one'     => ['1', true],
            'string zero'    => ['0', false],
            'string yes'     => ['yes', true],
            'string no'      => ['no', false],
            'string on'      => ['on', true],
            'string off'     => ['off', false],
            'null'           => [null, false],
            'empty array'    => [[], false],
            'object'         => [new \stdClass(), false],
        ];
    }

    /**
     * Test plain text sanitization and default fallback.
     *
     * @param mixed  $value
     * @param string $default
     * @param string $expected
     * @return void
     */
    #[DataProvider('stringProvider')]
    public function testItSanitizesStrings(mixed $value, string $default, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::string($value, $default));
    }

    /** @return array<string, array{0: mixed, 1: string, 2: string}> */
    public static function stringProvider(): array
    {
        return [
            'plain text'      => ['Hello World', '', 'Hello World'],
            'bold tags'       => ['<strong>Bold</strong>', '', 'Bold'],
            'script tags'     => ['<script>alert("x")</script>', '', 'alert("x")'],
            'null default'    => [null, 'fallback', 'fallback'],
            'null no default' => [null, '', ''],
            'array'           => [['a'], '', ''],
            'object'          => [new \stdClass(), '', ''],
            'integer cast'    => [123, '', '123'],
            'float cast'      => [1.5, '', '1.5'],
            'bool cast'       => [true, '', '1'],
            'trimmed'         => ['   spaced   ', '', 'spaced'],
        ];
    }

    /**
     * Test textarea sanitization keeps content but drops markup.
     *
     * @param mixed  $value
     * @param string $default
     * @param string $expected
     * @return void
     */
    #[DataProvider('textareaProvider')]
    public function testItSanitizesTextareas(mixed $value, string $default, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::textarea($value, $default));
    }

    /** @return array<string, array{0: mixed, 1: string, 2: string}> */
    public static function textareaProvider(): array
    {
        return [
            'paragraph tags' => ['<p>First</p><p>Second</p>', '', 'FirstSecond'],
            'line breaks'    => ["Line 1\nLine 2", '', "Line 1\nLine 2"],
            'null default'   => [null, 'fallback', 'fallback'],
            'null no default' => [null, '', ''],
            'array'          => [['a'], '', ''],
        ];
    }

    /**
     * Test integer sanitization and default fallback.
     *
     * @param mixed $value
     * @param int   $default
     * @param int   $expected
     * @return void
     */
    #[DataProvider('integerProvider')]
    public function testItSanitizesIntegers(mixed $value, int $default, int $expected): void
    {
        $this->assertSame($expected, Sanitizer::integer($value, $default));
    }

    /** @return array<string, array{0: mixed, 1: int, 2: int}> */
    public static function integerProvider(): array
    {
        return [
            'integer'          => [42, 0, 42],
            'numeric string'   => ['42', 0, 42],
            'float string'     => ['42.9', 0, 42],
            'float'            => [42.9, 0, 42],
            'negative'         => ['-7', 0, -7],
            'empty string'     => ['', 5, 5],
            'null'             => [null, 3, 3],
            'non numeric'      => ['abc', 10, 10],
            'array'            => [[], 4, 4],
            'zero'             => ['0', 9, 0],
        ];
    }

    /**
     * Test float sanitization and default fallback.
     *
     * @param mixed $value
     * @param float $default
     * @param float $expected
     * @return void
     */
    #[DataProvider('floatProvider')]
    public function testItSanitizesFloats(mixed $value, float $default, float $expected): void
    {
        $this->assertSame($expected, Sanitizer::float($value, $default));
    }

    /** @return array<string, array{0: mixed, 1: float, 2: float}> */
    public static function floatProvider(): array
    {
        return [
            'float'          => [3.14, 0.0, 3.14],
            'float string'   => ['3.14', 0.0, 3.14],
            'integer string' => ['42', 0.0, 42.0],
            'negative'       => ['-1.5', 0.0, -1.5],
            'empty string'   => ['', 1.5, 1.5],
            'null'           => [null, 2.5, 2.5],
            'non numeric'    => ['test', 0.5, 0.5],
            'array'          => [[], 8.5, 8.5],
        ];
    }

    /**
     * Test email sanitization and default fallback.
     *
     * @param mixed  $value
     * @param string $default
     * @param string $expected
     * @return void
     */
    #[DataProvider('emailProvider')]
    public function testItSanitizesEmails(mixed $value, string $default, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::email($value, $default));
    }

    /** @return array<string, array{0: mixed, 1: string, 2: string}> */
    public static function emailProvider(): array
    {
        return [
            'valid'          => ['test@example.com', '', 'test@example.com'],
            'valid plus tag' => ['user+tag@example.com', '', 'user+tag@example.com'],
            'uppercase host' => ['test@EXAMPLE.COM', '', 'test@EXAMPLE.COM'],
            'invalid'        => ['not-an-email', 'default@example.com', 'default@example.com'],
            'empty'          => ['', 'fallback@example.com', 'fallback@example.com'],
            'null'           => [null, 'n@example.com', 'n@example.com'],
            'array'          => [[], 'a@example.com', 'a@example.com'],
        ];
    }

    /**
     * Test URL sanitization and default fallback.
     *
     * @param mixed  $value
     * @param string $default
     * @param string $expected
     * @return void
     */
    #[DataProvider('urlProvider')]
    public function testItSanitizesUrls(mixed $value, string $default, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::url($value, $default));
    }

    /** @return array<string, array{0: mixed, 1: string, 2: string}> */
    public static function urlProvider(): array
    {
        return [
            'http'             => ['http://example.com', '', 'http://example.com'],
            'https with query' => ['https://example.com/path?query=1', '', 'https://example.com/path?query=1'],
            'javascript'       => ['javascript:alert(1)', 'https://fallback.com', 'https://fallback.com'],
            'malformed'        => ['not a url', 'http://fallback.com', 'http://fallback.com'],
            'empty'            => ['', 'http://def.com', 'http://def.com'],
            'null'             => [null, 'http://def.com', 'http://def.com'],
            'array'            => [[], 'http://def.com', 'http://def.com'],
        ];
    }

    /**
     * Test array of strings sanitization.
     *
     * @param mixed $value
     * @param array<mixed> $expected
     * @return void
     */
    #[DataProvider('arrayOfStringsProvider')]
    public function testItSanitizesArraysOfStrings(mixed $value, array $expected): void
    {
        $this->assertSame($expected, Sanitizer::arrayOfStrings($value));
    }

    /** @return array<string, array{0: mixed, 1: array<mixed>}> */
    public static function arrayOfStringsProvider(): array
    {
        return [
            'simple array'  => [['one', 'two', 'three'], ['one', 'two', 'three']],
            'with markup'   => [['<b>bold</b>', 'normal'], ['bold', 'normal']],
            'mixed types'   => [[123, '<i>x</i>'], ['123', 'x']],
            'associative'   => [['key' => '<b>value</b>'], ['key' => 'value']],
            'empty array'   => [[], []],
            'not an array'  => ['string', []],
            'null'          => [null, []],
            'nested arrays' => [[['a'], 'b'], ['', 'b']],
        ];
    }

    /**
     * Test the type based cast dispatcher.
     *
     * @param mixed  $value
     * @param string $type
     * @param mixed  $default
     * @param mixed  $expected
     * @return void
     */
    #[DataProvider('castProvider')]
    public function testItCastsValuesByType(mixed $value, string $type, mixed $default, mixed $expected): void
    {
        $result = Sanitizer::cast($value, $type, $default);

        if (is_float($expected)) {
            $this->assertEqualsWithDelta($expected, $result, 0.0001);

            return;
        }

        $this->assertSame($expected, $result);
    }

    /** @return array<string, array{0: mixed, 1: string, 2: mixed, 3: mixed}> */
    public static function castProvider(): array
    {
        return [
            'string'             => ['<b>Hello</b>', 'string', 'def', 'Hello'],
            'string null'        => [null, 'string', 'fallback', 'fallback'],
            'boolean truthy'     => ['yes', 'boolean', null, true],
            'boolean falsy'      => [0, 'boolean', true, false],
            'integer'            => ['123', 'integer', 0, 123],
            'integer fallback'   => ['abc', 'integer', 50, 50],
            'float'              => ['3.14', 'float', 0, 3.14],
            'float fallback'     => ['bad', 'float', 1.5, 1.5],
            'email'              => ['user@domain.com', 'email', '', 'user@domain.com'],
            'email fallback'     => ['invalid', 'email', 'd@d.com', 'd@d.com'],
            'url'                => ['https://site.com', 'url', '', 'https://site.com'],
            'url fallback'       => ['bad url', 'url', 'http://x.com', 'http://x.com'],
            'textarea'           => ['<p>Hi</p>', 'textarea', '', 'Hi'],
            'unknown type'       => ['<b>Text</b>', 'unknown', 'def', 'Text'],
            'non scalar default' => ['value', 'string', [], 'value'],
        ];
    }

    /**
     * Test the cast dispatcher coerces unusable defaults to typed values.
     *
     * @return void
     */
    public function testItCoercesNonNumericDefaultsForNumericTypes(): void
    {
        $this->assertSame(0, Sanitizer::cast('nope', 'integer', 'not-a-number'));
        $this->assertSame(0.0, Sanitizer::cast('nope', 'float', 'not-a-number'));
        $this->assertSame('', Sanitizer::cast(null, 'string', null));
        $this->assertSame('', Sanitizer::cast(null, 'email', null));
        $this->assertSame('', Sanitizer::cast(null, 'url', null));
        $this->assertSame('', Sanitizer::cast(null, 'textarea', null));
    }
}
