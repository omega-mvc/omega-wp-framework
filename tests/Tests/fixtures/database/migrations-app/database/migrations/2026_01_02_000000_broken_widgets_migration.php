<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * Fixture migration that fails on purpose so the migrator error handling
 * branch can be observed through the WordPress runtime registry.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

use Omega\Database\Migrations\AbstractMigration;
use Tests\Routing\WordPressRuntime;

return new class () extends AbstractMigration {
    /**
     * Record the attempt before failing.
     */
    public function up(): void
    {
        WordPressRuntime::$options['broken_migration_up'] = true;

        throw new RuntimeException('migration exploded');
    }

    /**
     * Record that the migration has been rolled back.
     */
    public function down(): void
    {
        WordPressRuntime::$options['broken_migration_down'] = true;
    }
};
