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

use Omega\Application\Application;
use Omega\Config\ConfigRepository;
use Omega\Config\ConfigServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;
use UnexpectedValueException;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Tests the ConfigServiceProvider binding.
 *
 * @category  Tests
 * @package   Config
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(ConfigServiceProvider::class)]
final class ConfigServiceProviderTest extends ConfigTestCase
{
    /**
     * Test config files are loaded and namespaced by file name.
     */
    public function testLoadsConfigFilesNamespacedByFileName(): void
    {
        $repository = $this->makeApplication()->resolve('config');

        $this->assertInstanceOf(ConfigRepository::class, $repository);
        $this->assertSame('local', $repository->get('app.environment'));
        $this->assertSame('Sample Plugin', $repository->get('app.name'));
        $this->assertTrue($repository->get('app.debug'));
    }

    /**
     * Test an application without a config directory yields an empty repository.
     */
    public function testReturnsEmptyRepositoryWithoutConfigDirectory(): void
    {
        $application = new Application('app', $this->emptyBasePath());

        $config = $application->resolve('config');

        $this->assertInstanceOf(ConfigRepository::class, $config);
        $this->assertSame([], $config->getAll());
    }

    /**
     * Test the config service is registered as a singleton.
     */
    public function testConfigServiceIsSingleton(): void
    {
        $application = $this->makeApplication();

        $this->assertSame($application->resolve('config'), $application->resolve('config'));
    }

    /**
     * Test a config file that does not return an array raises an error.
     */
    public function testRejectsConfigFileThatDoesNotReturnAnArray(): void
    {
        $basePath = sys_get_temp_dir() . '/omega-config-' . uniqid();

        $this->createConfig($basePath, 'broken', 'return "not-array";');

        $application = new Application('app', $basePath);

        try {
            $application->resolve('config');
            $this->fail('Expected UnexpectedValueException for a non-array config file.');
        } catch (Throwable $e) {
            $this->assertInstanceOf(UnexpectedValueException::class, $e);
        } finally {
            $this->removeConfig($basePath);
        }
    }

    /**
     * Test the deterministic sorted load order is applied.
     */
    public function testSortsConfigFilesByName(): void
    {
        $basePath = sys_get_temp_dir() . '/omega-config-' . uniqid();

        $this->createConfig($basePath, 'z-last', 'return ["value" => "last"];');
        $this->createConfig($basePath, 'a-first', 'return ["value" => "first"];');

        $application = new Application('app', $basePath);

        $repository = $application->resolve('config');

        $this->assertInstanceOf(ConfigRepository::class, $repository);
        $this->assertSame(['a-first', 'z-last'], array_keys($repository->getAll()));

        $this->removeConfig($basePath);
    }

    /**
     * Write a config file returning the given expression in a temp application.
     *
     * @param string $basePath Temporary application base path.
     * @param string $key Config file key.
     * @param string $returnExpression PHP expression the file returns.
     */
    private function createConfig(string $basePath, string $key, string $returnExpression): void
    {
        $configDir = $basePath . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }
        file_put_contents($configDir . '/' . $key . '.php', '<?php ' . $returnExpression . ';');
    }

    /**
     * Recursively remove a temp application directory.
     *
     * @param string $basePath Temporary application base path.
     */
    private function removeConfig(string $basePath): void
    {
        $configDir = $basePath . '/config';
        $files     = glob($configDir . '/*.php');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($configDir);
        rmdir($basePath);
    }
}
