<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * Minimal plugin application used to drive the migrator with a controlled
 * base path and a controlled list of extra migration folders.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database\Fixtures;

use Omega\Application\Application;

/**
 * Plugin application with an injectable base path and migration folders.
 *
 * @category  Tests
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class MigrationApplication extends Application
{
    /**
     * Create the application around an arbitrary base path.
     *
     * @param string                $basePath         Base path returned to the migrator.
     * @param array<int, string>    $migrationFolders Extra folders registered for migrations.
     */
    public function __construct(string $basePath, array $migrationFolders = [])
    {
        parent::__construct('migrations', $basePath);

        $this->migrationFolders = $migrationFolders;
    }
}
