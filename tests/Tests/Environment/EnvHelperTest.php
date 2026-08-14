<?php

/**
 * Part of Omega - Tests Environment Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Environment;

use Omega\Environment\Env;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function Omega\Environment\env;
use function putenv;

/**
 * Tests the env() environment helper.
 *
 * Ensures the helper reads values from the loaded environment, returns the
 * default when a key is missing, casts common string values, and falls back
 * to the system environment.
 *
 * @category  Tests
 * @package   Environment
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversFunction('Omega\Environment\env')]
final class EnvHelperTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setValues([]);
    }

    protected function tearDown(): void
    {
        $this->setValues([]);

        putenv('OMEGA_HELPER_TEST');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function setValues(array $values): void
    {
        $valuesProp = (new ReflectionClass(Env::class))->getProperty('values');
        $valuesProp->setValue(null, $values);
    }

    /**
     * Test the namespaced helper is always declared.
     *
     * Regression: the former `function_exists('env')` guard checked the
     * *global* scope, so if a global `env()` happened to exist the namespaced
     * `Omega\Environment\env` was silently never declared.
     */
    public function testHelperIsDeclared(): void
    {
        $this->assertTrue(function_exists('Omega\Environment\env'));
    }

    /**
     * Test the helper returns a value from the loaded environment.
     */
    public function testReturnsLoadedValue(): void
    {
        $this->setValues(['APP_NAME' => 'Omega']);

        $this->assertSame('Omega', env('APP_NAME'));
    }

    /**
     * Test the loaded value takes precedence over the default.
     */
    public function testLoadedValueTakesPrecedenceOverDefault(): void
    {
        $this->setValues(['APP_NAME' => 'Omega']);

        $this->assertSame('Omega', env('APP_NAME', 'default'));
    }

    /**
     * Test the helper returns the default when the key is not found.
     */
    public function testReturnsDefaultWhenKeyNotFound(): void
    {
        $this->setValues(['APP_NAME' => 'Omega']);

        $this->assertSame('fallback', env('NON_EXISTING_KEY', 'fallback'));
    }

    /**
     * Test the helper returns null when the key is not found and no default is given.
     */
    public function testReturnsNullWhenKeyNotFoundWithoutDefault(): void
    {
        $this->setValues([]);

        $this->assertNull(env('NON_EXISTING_KEY'));
    }

    /**
     * Test the helper casts common string values from the environment.
     */
    public function testCastsLoadedValues(): void
    {
        $this->setValues([
            'APP_DEBUG'   => 'true',
            'APP_TIMEOUT' => '42',
            'APP_EMPTY'   => 'empty',
            'APP_NULL'    => 'null',
        ]);

        $this->assertTrue(env('APP_DEBUG'));
        $this->assertSame(42, env('APP_TIMEOUT'));
        $this->assertSame('', env('APP_EMPTY'));
        $this->assertNull(env('APP_NULL'));
    }

    /**
     * Test the helper falls back to the system environment.
     */
    public function testFallsBackToSystemEnvironment(): void
    {
        putenv('OMEGA_HELPER_TEST=hello');

        $this->assertSame('hello', env('OMEGA_HELPER_TEST'));
    }
}
