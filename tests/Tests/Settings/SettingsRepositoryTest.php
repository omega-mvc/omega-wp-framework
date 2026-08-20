<?php

/**
 * Part of Omega - Tests Settings Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Settings;

use Omega\Application\ApplicationInterface;
use Omega\Settings\SettingsRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Routing\WordPressRuntime;

/**
 * Tests the SettingsRepository retrieval, casting and persistence behaviour.
 *
 * @category  Tests
 * @package   Settings
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(SettingsRepository::class)]
final class SettingsRepositoryTest extends TestCase
{
    /**
     * Reset the WordPress option stub state.
     */
    protected function setUp(): void
    {
        parent::setUp();

        WordPressRuntime::reset();
    }

    /**
     * Test stored settings win over the defaults.
     */
    public function testMergesDefaultsWithStoredSettings(): void
    {
        $repository = $this->makeRepository(
            ['theme' => 'light', 'nested' => ['level' => 1]],
            ['theme' => 'dark']
        );

        $this->assertSame('dark', $repository->get('theme'));
        $this->assertSame(1, $repository->get('nested.level'));
    }

    /**
     * Test a stored numeric list replaces the default list.
     *
     * Lists keyed by integers are treated as whole values rather than
     * recursively merged associative arrays.
     */
    public function testMergeReplacesDefaultListWithStoredNumericList(): void
    {
        $repository = $this->makeRepository(['items' => ['default']], ['items' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $repository->get('items'));
    }

    /**
     * Test a stored array replaces a scalar default value.
     */
    public function testMergeStoredArrayOverridesScalarDefault(): void
    {
        $repository = $this->makeRepository(['x' => 1], ['x' => [1, 2]]);

        $this->assertSame([1, 2], $repository->get('x'));
    }

    /**
     * Test a stored empty array replaces the default list.
     */
    public function testMergeStoredEmptyArrayReplacesDefault(): void
    {
        $repository = $this->makeRepository(['items' => ['a']], ['items' => []]);

        $this->assertSame([], $repository->get('items'));
    }

    /**
     * Test the default is returned for a missing key.
     */
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $repository = $this->makeRepository();

        $this->assertNull($repository->get('missing'));
        $this->assertSame('fallback', $repository->get('missing', 'fallback'));
    }

    /**
     * Test the default is returned when traversal continues past a missing segment.
     */
    public function testGetReturnsDefaultAfterMissingSegment(): void
    {
        $repository = $this->makeRepository([], ['mail' => ['host' => 'mx.example']]);

        $this->assertNull($repository->get('mail.port.deep'));
    }

    /**
     * Test the default is returned when traversal crosses a scalar value.
     */
    public function testGetReturnsDefaultWhenTraversingThroughScalar(): void
    {
        $repository = $this->makeRepository([], ['flag' => 'yes']);

        $this->assertNull($repository->get('flag.deep'));
    }

    /**
     * Test the default is returned when a stored value is null.
     */
    public function testGetReturnsDefaultForNullValue(): void
    {
        $repository = $this->makeRepository([], ['empty' => null]);

        $this->assertSame('fallback', $repository->get('empty', 'fallback'));
        $this->assertFalse($repository->has('empty'));
    }

    /**
     * Test a nested value is resolved through dot notation.
     */
    public function testGetNestedValue(): void
    {
        $repository = $this->makeRepository([], ['mail' => ['host' => 'mx.example']]);

        $this->assertSame('mx.example', $repository->get('mail.host'));
        $this->assertTrue($repository->has('mail.host'));
        $this->assertFalse($repository->has('mail.port'));
    }

    /**
     * Test string() sanitizes the resolved value.
     */
    public function testStringSanitizesValue(): void
    {
        $repository = $this->makeRepository([], ['title' => '<b>Title</b>']);

        $this->assertSame('Title', $repository->string('title'));
        $this->assertSame('fallback', $repository->string('missing', 'fallback'));
    }

    /**
     * Test string() returns empty when the key is missing and no default is given.
     */
    public function testStringReturnsEmptyWhenNoDefault(): void
    {
        $this->assertSame('', $this->makeRepository()->string('missing'));
    }

    /**
     * Test boolean() recognizes the truthy variants.
     */
    public function testBooleanTruthyVariants(): void
    {
        $repository = $this->makeRepository([], ['yes' => 'yes', 'one' => '1', 'int' => 1, 'bool' => true]);

        $this->assertTrue($repository->boolean('yes'));
        $this->assertTrue($repository->boolean('one'));
        $this->assertTrue($repository->boolean('int'));
        $this->assertTrue($repository->boolean('bool'));
    }

    /**
     * Test boolean() recognizes the falsy variants.
     */
    public function testBooleanFalsyVariants(): void
    {
        $repository = $this->makeRepository([], ['no' => 'no', 'zero' => '0', 'false' => 'false', 'zero_int' => 0]);

        $this->assertFalse($repository->boolean('no'));
        $this->assertFalse($repository->boolean('zero'));
        $this->assertFalse($repository->boolean('false'));
        $this->assertFalse($repository->boolean('zero_int'));
    }

    /**
     * Test boolean() treats 'on' as falsy.
     *
     * Unlike the ConfigRepository, the SettingsRepository only maps
     * 'yes'/'1' to true, so the 'on' variant falls through to false.
     * This documents the current quirk.
     */
    public function testBooleanTreatsOnAsFalsy(): void
    {
        $this->assertFalse($this->makeRepository([], ['switch' => 'on'])->boolean('switch'));
    }

    /**
     * Test integer() casts the resolved value.
     */
    public function testIntegerCasts(): void
    {
        $repository = $this->makeRepository([], ['port' => '3306']);

        $this->assertSame(3306, $repository->integer('port'));
        $this->assertSame(0, $repository->integer('missing'));
        $this->assertSame(7, $repository->integer('missing', 7));
    }

    /**
     * Test integer() returns zero when the key is missing and no default is given.
     */
    public function testIntegerReturnsZeroWhenNoDefault(): void
    {
        $this->assertSame(0, $this->makeRepository()->integer('missing'));
    }

    /**
     * Test integer() casts a float config value to int.
     */
    public function testIntegerCastsFloatToInt(): void
    {
        $repository = $this->makeRepository(['pi' => 3.14]);

        $this->assertSame(3, $repository->integer('pi'));
    }

    /**
     * Test integer() returns the non-null default when the resolved value
     * is not int, float, or string (e.g. array).
     */
    public function testIntegerReturnsNonNullDefaultForNonNumericValue(): void
    {
        $repository = $this->makeRepository(['data' => ['a', 'b']]);

        $this->assertSame(5, $repository->integer('data', 5));
    }

    /**
     * Test update() persists a simple key through update_option.
     */
    public function testUpdateSimpleKeyPersists(): void
    {
        $repository = $this->makeRepository(['theme' => 'light']);

        $this->assertTrue($repository->update('theme', 'dark'));

        $this->assertSame('dark', $repository->get('theme'));
        $this->assertSame(['theme' => 'dark'], WordPressRuntime::$options['app_settings']);
        $this->assertSame(['app_settings', ['theme' => 'dark'], true], WordPressRuntime::$optionUpdates[0]);
    }

    /**
     * Test update() persists a nested key.
     */
    public function testUpdateNestedKeyPersists(): void
    {
        $repository = $this->makeRepository();

        $this->assertTrue($repository->update('mail.host', 'mx.example'));

        $this->assertSame('mx.example', $repository->get('mail.host'));
        $this->assertSame(['mail' => ['host' => 'mx.example']], WordPressRuntime::$options['app_settings']);
    }

    /**
     * Test update() preserves existing sibling keys.
     */
    public function testUpdatePreservesExistingSiblings(): void
    {
        $repository = $this->makeRepository(['a' => ['x' => 1, 'y' => 2]]);

        $this->assertTrue($repository->update('a.x', 9));

        $this->assertSame(['x' => 9, 'y' => 2], $repository->getAll()['a']);
    }

    /**
     * Test update() converts booleans into the string variants.
     */
    public function testUpdateConvertsBooleansToStrings(): void
    {
        $repository = $this->makeRepository();

        $this->assertTrue($repository->update('flag', true));
        $this->assertSame('yes', $repository->get('flag'));

        $this->assertTrue($repository->update('flag', false));
        $this->assertSame('no', $repository->get('flag'));
    }

    /**
     * Test update() recursively processes values inside nested arrays.
     */
    public function testUpdateProcessesNestedArrayValues(): void
    {
        $repository = $this->makeRepository();

        $this->assertTrue($repository->update('settings', ['debug' => true, 'ports' => [80, 443]]));

        $this->assertSame(['debug' => 'yes', 'ports' => [80, 443]], $repository->get('settings'));
        $this->assertSame(
            ['settings' => ['debug' => 'yes', 'ports' => [80, 443]]],
            WordPressRuntime::$options['app_settings']
        );
    }

    /**
     * Test delete() removes a key and persists the remaining settings.
     */
    public function testDeleteRemovesKeyAndSaves(): void
    {
        $repository = $this->makeRepository(['theme' => 'dark', 'other' => 1]);

        $this->assertTrue($repository->delete('theme'));

        $this->assertFalse($repository->has('theme'));
        $this->assertSame(1, $repository->get('other'));
        $this->assertSame(['other' => 1], WordPressRuntime::$options['app_settings']);
    }

    /**
     * Test delete() returns false for a missing key.
     */
    public function testDeleteReturnsFalseForMissingKey(): void
    {
        $repository = $this->makeRepository(['theme' => 'dark']);

        $this->assertFalse($repository->delete('missing'));
        $this->assertFalse($repository->delete('missing.deep'));
    }

    /**
     * Test delete() returns false when the path crosses a scalar value.
     */
    public function testDeleteReturnsFalseWhenTraversingThroughScalar(): void
    {
        $repository = $this->makeRepository([], ['flag' => 'yes']);

        $this->assertFalse($repository->delete('flag.deep'));
    }

    /**
     * Test delete() removes a nested key while keeping its siblings.
     */
    public function testDeleteNestedKey(): void
    {
        $repository = $this->makeRepository(['mail' => ['host' => 'mx.example', 'port' => 25]]);

        $this->assertTrue($repository->delete('mail.host'));

        $this->assertNull($repository->get('mail.host'));
        $this->assertSame(25, $repository->get('mail.port'));
        $this->assertSame(['mail' => ['port' => 25]], WordPressRuntime::$options['app_settings']);
    }

    /**
     * Test getAll() returns the merged configuration.
     */
    public function testGetAllReturnsFullConfig(): void
    {
        $repository = $this->makeRepository(['theme' => 'light'], ['mode' => 'auto']);

        $this->assertSame(['theme' => 'light', 'mode' => 'auto'], $repository->getAll());
    }

    /**
     * Build a repository backed by an app stub and the given defaults/saved data.
     *
     * @param array<string, mixed> $defaults Default settings
     * @param array<string, mixed> $saved    Stored option value
     *
     * @return SettingsRepository Repository instance
     */
    private function makeRepository(array $defaults = [], array $saved = []): SettingsRepository
    {
        if ($saved !== []) {
            WordPressRuntime::$options['app_settings'] = $saved;
        }

        $app = $this->createStub(ApplicationInterface::class);
        $app->method('getIdAsUnderscore')->willReturn('app');

        return new SettingsRepository($app, $defaults);
    }
}
