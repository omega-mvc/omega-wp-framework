<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * Fixture migration registered through an additional migration folder, so the
 * migrator has to merge the folder files with the application ones.
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
     * Record that the extra folder migration has been applied.
     */
    public function up(): void
    {
        WordPressRuntime::$options['gadgets_migration_up'] = true;
    }

    /**
     * Record that the extra folder migration has been rolled back.
     */
    public function down(): void
    {
        WordPressRuntime::$options['gadgets_migration_down'] = true;
    }
};
