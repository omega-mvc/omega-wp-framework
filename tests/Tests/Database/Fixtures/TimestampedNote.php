<?php

/**
 * Part of Omega - Tests Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Database\Fixtures;

use Omega\Database\ORM\AbstractModel;

/**
 * Model with timestamps, an explicit table and a custom prefix.
 *
 * @category  Tests
 * @package   Database
 * @subpackage Fixtures
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class TimestampedNote extends AbstractModel
{
    /**
     * Enable the automatic timestamps.
     */
    public bool $timestamps = true;

    /**
     * Database table name.
     */
    protected string $table = 'notes';

    /**
     * Table name prefix.
     */
    protected string $prefix = 'lab_';
}
