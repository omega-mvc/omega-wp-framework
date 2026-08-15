<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Application\Support;

use Omega\Application\Application;

/**
 * Concrete application used to drive the AbstractApplication coverage.
 *
 * It extends the real {@see Application} so every abstract interface method is
 * already implemented, while only the CLI detection is overridden to let tests
 * exercise both the CLI and non-CLI registration branches of the kernel layer.
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class AbstractApplicationStub extends Application
{
    /** @var bool Whether the application should behave as a CLI process. */
    private bool $cli;

    /**
     * Create a new stub application.
     *
     * @param string $id Unique application identifier.
     * @param string $basePath Absolute path to the application root directory.
     * @param bool $cli Whether to simulate a CLI process. Defaults to true.
     */
    public function __construct(string $id, string $basePath, bool $cli = true)
    {
        $this->cli = $cli;

        parent::__construct($id, $basePath);
    }

    /**
     * {@inheritdoc}
     */
    protected function isCli(): bool
    {
        return $this->cli;
    }
}
