<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * Migrator that never reports a deploy context, so the web branch of
 * Migrator::maybeCreateMigrationsTable() stays reachable.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database\Fixtures;

use Omega\Database\Migrations\Migrator;

/**
 * Migrator pinned to a web request context.
 *
 * PHP_SAPI is 'cli' for the entire PHPUnit run and cannot be changed at runtime,
 * so the web branch that only logs a failing migrations table is unreachable
 * unless the deploy detection is overridable.
 *
 * @category  Tests
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class WebContextMigrator extends Migrator
{
    /**
     * {@inheritdoc}
     */
    protected function isDeployContext(): bool
    {
        return false;
    }
}
