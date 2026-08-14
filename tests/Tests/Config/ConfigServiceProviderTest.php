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

        $this->assertSame([], $application->resolve('config')->getAll());
    }

    /**
     * Test the config service is registered as a singleton.
     */
    public function testConfigServiceIsSingleton(): void
    {
        $application = $this->makeApplication();

        $this->assertSame($application->resolve('config'), $application->resolve('config'));
    }
}
