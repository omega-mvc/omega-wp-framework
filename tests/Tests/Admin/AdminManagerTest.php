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

use Omega\Admin\AdminManager;
use PHPUnit\Framework\Attributes\DataProvider;

use function remove_all_actions;

/**
 * Test the AdminManager class.
 *
 * @category  Tests
 * @package   Admin
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class AdminManagerTest extends AdminTestCase
{
    /** AdminManager instance under test. */
    private AdminManager $manager;

    /**
     * Boot a fresh manager with a clean hook registry.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new AdminManager();

        remove_all_actions('in_admin_header');
        remove_all_actions('user_admin_notices');
        remove_all_actions('admin_notices');
    }

    /**
     * Drop the registered hooks and the simulated request query.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        remove_all_actions('in_admin_header');
        remove_all_actions('user_admin_notices');
        remove_all_actions('admin_notices');

        unset($_GET['page']);
    }

    /**
     * Register a no-op callback on the given notice hooks.
     *
     * @return void
     */
    private function registerNoticeActions(): void
    {
        $this->manager->addNotice(static function (): void {
        });
        $this->manager->addNotice(static function (): void {
        });
    }

    /**
     * Test the manager can be instantiated.
     *
     * @return void
     */
    public function testItInstantiatesTheManager(): void
    {
        $this->assertInstanceOf(AdminManager::class, $this->manager);
    }

    /**
     * Test init() registers the notice hiding hook with priority 99.
     *
     * @return void
     */
    public function testInitRegistersTheHideNoticesHook(): void
    {
        $this->manager->init();

        $this->assertSame(99, has_action('in_admin_header', [$this->manager, 'hideNotices']));
    }

    /**
     * Test no page query returns "not hidden".
     *
     * @return void
     */
    public function testMaybeHideNoticesIsFalseWithoutPageQuery(): void
    {
        unset($_GET['page']);

        $this->assertFalse($this->manager->maybeHideNotices());
    }

    /**
     * Test a page that was never registered returns "not hidden".
     *
     * @return void
     */
    public function testMaybeHideNoticesIsFalseForUnknownPage(): void
    {
        $_GET['page'] = 'unregistered-page';

        $this->assertFalse($this->manager->maybeHideNotices());
    }

    /**
     * Test a registered page returns "hidden".
     *
     * @return void
     */
    public function testMaybeHideNoticesIsTrueForRegisteredPage(): void
    {
        $this->manager->addHiddenNoticesPage('omega-settings');
        $_GET['page'] = 'omega-settings';

        $this->assertTrue($this->manager->maybeHideNotices());
    }

    /**
     * Test hidden pages are matched exactly, not by prefix.
     *
     * @return void
     */
    public function testMaybeHideNoticesRequiresAnExactPageMatch(): void
    {
        $this->manager->addHiddenNoticesPage('omega');
        $_GET['page'] = 'omega-settings';

        $this->assertFalse($this->manager->maybeHideNotices());
    }

    /**
     * Test several hidden pages can be registered.
     *
     * @param string $page
     * @return void
     */
    #[DataProvider('hiddenPageProvider')]
    public function testAddHiddenNoticesPageRegistersEachId(string $page): void
    {
        $this->manager->addHiddenNoticesPage($page);
        $_GET['page'] = $page;

        $this->assertTrue($this->manager->maybeHideNotices());
    }

    /** @return array<string, array{0: string}> */
    public static function hiddenPageProvider(): array
    {
        return [
            'dashed slug' => ['omega-settings'],
            'underscored' => ['omega_settings_page'],
            'numeric id'  => ['123'],
        ];
    }

    /**
     * Test hiding is skipped when the current page is not registered.
     *
     * @return void
     */
    public function testHideNoticesKeepsActionsWhenThePageIsNotHidden(): void
    {
        $this->registerNoticeActions();
        $_GET['page'] = 'other-page';

        $this->manager->hideNotices();

        $this->assertTrue(has_action('user_admin_notices'));
        $this->assertTrue(has_action('admin_notices'));
    }

    /**
     * Test hiding is skipped when no page query is present.
     *
     * @return void
     */
    public function testHideNoticesKeepsActionsWithoutPageQuery(): void
    {
        $this->registerNoticeActions();
        unset($_GET['page']);

        $this->manager->hideNotices();

        $this->assertTrue(has_action('user_admin_notices'));
        $this->assertTrue(has_action('admin_notices'));
    }

    /**
     * Test hiding removes every notice callback for registered pages.
     *
     * @return void
     */
    public function testHideNoticesRemovesActionsForRegisteredPage(): void
    {
        $this->manager->addHiddenNoticesPage('hidden-page');
        $_GET['page'] = 'hidden-page';
        $this->registerNoticeActions();

        $this->manager->hideNotices();

        $this->assertFalse(has_action('user_admin_notices'));
        $this->assertFalse(has_action('admin_notices'));
    }

    /**
     * Test hiding leaves unrelated hooks untouched.
     *
     * @return void
     */
    public function testHideNoticesKeepsUnrelatedHooks(): void
    {
        $this->manager->addHiddenNoticesPage('hidden-page');
        $_GET['page'] = 'hidden-page';
        $this->registerNoticeActions();
        add_action('omega_unrelated_hook', 'omega_test_unrelated');

        $this->manager->hideNotices();

        $this->assertTrue(has_action('omega_unrelated_hook'));

        remove_all_actions('omega_unrelated_hook');
    }

    /**
     * Test the reserved rendering hook stays a no-op.
     *
     * @return void
     */
    public function testSilenceRenderIsANoOp(): void
    {
        $this->registerNoticeActions();

        ob_start();
        $this->manager->silenceRender();
        $output = ob_get_clean();

        $this->assertSame('', $output);
        $this->assertTrue(has_action('user_admin_notices'));
        $this->assertTrue(has_action('admin_notices'));
    }
}
