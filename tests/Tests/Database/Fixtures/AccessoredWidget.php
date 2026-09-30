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
use Omega\Database\ORM\Casts\Attribute;

/**
 * Model exposing accessors through the Attribute object.
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
final class AccessoredWidget extends AbstractModel
{
    /**
     * Database table name.
     */
    protected string $table = 'widgets';

    /**
     * Read only accessor.
     *
     * @return Attribute The accessor definition.
     */
    public function label(): Attribute
    {
        return Attribute::get(static fn (mixed $value): string => 'label:' . (string) $value);
    }

    /**
     * Read and write accessor.
     *
     * @return Attribute The accessor definition.
     */
    public function slug(): Attribute
    {
        return Attribute::make(
            static fn (mixed $value): string => strtoupper((string) $value),
            static fn (mixed $value): string => strtolower((string) $value)
        );
    }

    /**
     * Write only accessor.
     *
     * @return Attribute The mutator definition.
     */
    public function code(): Attribute
    {
        return Attribute::set(static fn (mixed $value): string => 'code:' . (string) $value);
    }

    /**
     * Accessor shaped method that does not return an Attribute.
     *
     * @return string A plain value.
     */
    public function plain(): string
    {
        return 'plain';
    }
}
