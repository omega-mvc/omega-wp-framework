<?php

declare(strict_types=1);

namespace Tests\Environment\Support;

use Omega\Environment\Env;
use ReflectionProperty;

use function dirname;
use function Omega\Application\slash;

use const DIRECTORY_SEPARATOR;

/**
 * Static helpers shared by the environment test suite.
 *
 * Env keeps its loaded variables in a protected static store, so the test
 * suite has to seed and clear it through reflection.
 */
final class EnvironmentFixture
{
    /**
     * The keys declared by the .env.test fixture.
     *
     * @var array<int, string>
     */
    public const array FIXTURE_KEYS = [
        'APP_NAME',
        'APP_DEBUG',
        'APP_TIMEOUT',
        'APP_EMPTY',
        'APP_NULL',
    ];

    /**
     * The absolute path of the directory holding the environment fixture.
     */
    public static function fixturePath(): string
    {
        return slash(path: dirname(__DIR__, 2) . '/fixtures/environment') . DIRECTORY_SEPARATOR;
    }

    /**
     * Replace the whole loaded store of Env.
     *
     * @param array<string, mixed> $values The store the next lookup reads from.
     */
    public static function setValues(array $values): void
    {
        self::valuesProperty()->setValue(null, $values);
    }

    /**
     * Drop the loaded store and every fixture key leaked into the process environment.
     */
    public static function reset(): void
    {
        self::setValues([]);

        foreach (self::FIXTURE_KEYS as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    /**
     * The protected static store backing Env::get().
     */
    private static function valuesProperty(): ReflectionProperty
    {
        // ReflectionProperty::setAccessible() is a no-op since PHP 8.1 and is
        // deprecated since PHP 8.5, so the property is writable as it is.
        return new ReflectionProperty(Env::class, 'values');
    }
}
