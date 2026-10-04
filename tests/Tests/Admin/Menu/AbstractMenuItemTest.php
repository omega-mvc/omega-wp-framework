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

namespace Tests\Admin\Menu;

use Omega\Admin\Menu\AbstractMenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Admin\Fixtures\SampleMenuItem;

use function array_keys;

/**
 * Test the AbstractMenuItem contract.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(AbstractMenuItem::class)]
final class AbstractMenuItemTest extends TestCase
{
    /**
     * Test a fresh menu item exposes the documented default values.
     *
     * @return void
     */
    public function testItExposesTheDefaultValues(): void
    {
        $item = new SampleMenuItem();

        $this->assertSame('', $item->getSlug());
        $this->assertSame('', $item->getTitle());
        $this->assertSame('manage_options', $item->getCapability());
        $this->assertSame('', $item->getIcon());
        $this->assertSame('', $item->getPath());
        $this->assertNull($item->getPosition());
    }

    /**
     * Test every setter returns the same instance for chaining.
     *
     * @return void
     */
    public function testEverySetterIsFluent(): void
    {
        $item = new SampleMenuItem();

        $this->assertSame($item, $item->slug('omega-tasks'));
        $this->assertSame($item, $item->title('Tasks'));
        $this->assertSame($item, $item->capability('edit_posts'));
        $this->assertSame($item, $item->icon('dashicons-lightbulb'));
        $this->assertSame($item, $item->position(42));
        $this->assertSame($item, $item->path('admin/tasks'));
        $this->assertSame($item, $item->view('tasks.index'));
        $this->assertSame($item, $item->scripts(['tasks']));
    }

    /**
     * Test the getters read back the assigned values.
     *
     * @return void
     */
    public function testTheGettersReadBackTheAssignedValues(): void
    {
        $item = (new SampleMenuItem())
            ->slug('omega-tasks')
            ->title('Tasks')
            ->capability('edit_posts')
            ->icon('dashicons-lightbulb')
            ->position(42)
            ->path('admin/tasks')
            ->view('tasks.index');

        $this->assertSame('omega-tasks', $item->getSlug());
        $this->assertSame('Tasks', $item->getTitle());
        $this->assertSame('edit_posts', $item->getCapability());
        $this->assertSame('dashicons-lightbulb', $item->getIcon());
        $this->assertSame(42, $item->getPosition());
        $this->assertSame('admin/tasks', $item->getPath());
    }

    /**
     * Test the position accepts any WordPress supported value.
     *
     * @param mixed $position Position value assigned to the menu item
     * @return void
     */
    #[DataProvider('positionProvider')]
    public function testThePositionAcceptsAnySupportedValue(mixed $position): void
    {
        $item = (new SampleMenuItem())->position($position);

        $this->assertSame($position, $item->getPosition());
    }

    /**
     * Positions accepted by the fluent setter.
     *
     * @return array<string, array{0: mixed}>
     */
    public static function positionProvider(): array
    {
        return [
            'integer'          => [42],
            'float'            => [4.5],
            'numeric string'   => ['25'],
            'null'             => [null],
        ];
    }

    /**
     * Test the array representation exposes the item metadata.
     *
     * @return void
     */
    public function testToArrayExposesTheItemMetadata(): void
    {
        $item = (new SampleMenuItem())
            ->slug('omega-tasks')
            ->title('Tasks')
            ->capability('edit_posts')
            ->icon('dashicons-lightbulb')
            ->view('tasks.index');

        $this->assertSame(
            [
                'slug'       => 'omega-tasks',
                'title'      => 'Tasks',
                'capability' => 'edit_posts',
                'icon'       => 'dashicons-lightbulb',
                'view'       => 'tasks.index',
            ],
            $item->toArray()
        );
    }

    /**
     * Test the array representation is limited to the documented keys.
     *
     * @return void
     */
    public function testToArrayOnlyExposesTheDocumentedKeys(): void
    {
        $item = (new SampleMenuItem())
            ->path('admin/tasks')
            ->position(42)
            ->scripts(['tasks']);

        $this->assertSame(
            ['slug', 'title', 'capability', 'icon', 'view'],
            array_keys($item->toArray())
        );
    }

    /**
     * Test the array representation of a fresh item uses the default capability.
     *
     * @return void
     */
    public function testToArrayUsesTheDefaultCapability(): void
    {
        $representation = (new SampleMenuItem())->toArray();

        $this->assertSame('manage_options', $representation['capability']);
        $this->assertSame('', $representation['slug']);
        $this->assertSame('', $representation['title']);
        $this->assertSame('', $representation['icon']);
        $this->assertSame('', $representation['view']);
    }
}
