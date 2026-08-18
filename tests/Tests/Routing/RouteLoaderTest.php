<?php

/**
 * Part of Omega - Tests Routing Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Routing;

use Omega\Application\ApplicationInterface;
use Omega\Routing\RouteLoader;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the RouteLoader file-loading behavior.
 *
 * Verifies that REST and admin route files are loaded from the correct paths,
 * additional registered files are included, and non-existent files are skipped.
 *
 * @category  Tests
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(RouteLoader::class)]
final class RouteLoaderTest extends RoutingTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/omega-route-loader-test-' . uniqid('', true);
        mkdir($this->tempDir . '/routes', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    // ── loadRestRoutes ──────────────────────────────────────────

    /**
     * loadRestRoutes() must load the default api.php route file.
     */
    public function testLoadRestRoutesLoadsDefaultApiRoute(): void
    {
        $this->createRouteFile('api.php', 'test_api_loaded');

        $app = $this->stubApp([], []);

        $loader = new RouteLoader($app);
        $loader->loadRestRoutes();

        $this->assertTrue($GLOBALS['test_api_loaded'] ?? false, 'Default api.php must be loaded.');
    }

    /**
     * loadRestRoutes() must load additional registered route files.
     */
    public function testLoadRestRoutesLoadsAdditionalRouteFiles(): void
    {
        $this->createRouteFile('api.php', 'test_api_loaded');

        $additionalFile = $this->tempDir . '/routes/custom-rest.php';
        file_put_contents($additionalFile, '<?php $GLOBALS["test_custom_rest"] = true;');

        $app = $this->stubApp([$additionalFile], []);

        $loader = new RouteLoader($app);
        $loader->loadRestRoutes();

        $this->assertTrue($GLOBALS['test_api_loaded'] ?? false, 'Default api.php must be loaded.');
        $this->assertTrue($GLOBALS['test_custom_rest'] ?? false, 'Additional rest route must be loaded.');
    }

    // ── loadAdminRoutes ─────────────────────────────────────────

    /**
     * loadAdminRoutes() must load the default admin.php route file.
     */
    public function testLoadAdminRoutesLoadsDefaultAdminRoute(): void
    {
        $this->createRouteFile('admin.php', 'test_admin_loaded');

        $app = $this->stubApp([], []);

        $loader = new RouteLoader($app);
        $loader->loadAdminRoutes();

        $this->assertTrue($GLOBALS['test_admin_loaded'] ?? false, 'Default admin.php must be loaded.');
    }

    /**
     * loadAdminRoutes() must load additional registered route files.
     */
    public function testLoadAdminRoutesLoadsAdditionalFiles(): void
    {
        $this->createRouteFile('admin.php', 'test_admin_loaded');

        $additionalFile = $this->tempDir . '/routes/custom-admin.php';
        file_put_contents($additionalFile, '<?php $GLOBALS["test_custom_admin"] = true;');

        $app = $this->stubApp([], [$additionalFile]);

        $loader = new RouteLoader($app);
        $loader->loadAdminRoutes();

        $this->assertTrue($GLOBALS['test_admin_loaded'] ?? false, 'Default admin.php must be loaded.');
        $this->assertTrue($GLOBALS['test_custom_admin'] ?? false, 'Additional admin route must be loaded.');
    }

    // ── load() — file existence ─────────────────────────────────

    /**
     * Non-existent route files must be silently skipped without breaking
     * the loading of existing files in the same batch.
     */
    public function testLoadSkipsNonExistentFiles(): void
    {
        $this->createRouteFile('api.php', 'test_api_loaded');

        $app = $this->stubApp(['/nonexistent/route.php'], []);

        $loader = new RouteLoader($app);
        $loader->loadRestRoutes();

        $this->assertTrue($GLOBALS['test_api_loaded'] ?? false, 'Existing file must still be loaded.');
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Creates a route file that sets a global flag when loaded.
     */
    private function createRouteFile(string $name, string $globalKey): void
    {
        file_put_contents(
            $this->tempDir . '/routes/' . $name,
            '<?php $GLOBALS["' . $globalKey . '"] = true;'
        );
    }

    /**
     * Builds a stub ApplicationInterface with the temp directory as base path.
     */
    private function stubApp(array $restFiles, array $adminFiles): ApplicationInterface
    {
        $app = $this->createStub(ApplicationInterface::class);
        $app->method('getBasePath')->willReturn($this->tempDir);
        $app->method('getRestRouteFiles')->willReturn($restFiles);
        $app->method('getAdminRouteFiles')->willReturn($adminFiles);

        return $app;
    }

    /**
     * Recursively removes a directory and all its contents.
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
