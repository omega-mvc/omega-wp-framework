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
 * Model pinning nothing at all, so every fallback has to kick in.
 *
 * The empty table name forces the derived table name to be used, while the
 * cast definition is neither a string nor a cast object, so it resolves to
 * no cast at all and the raw value survives untouched.
 *
 * @property mixed $marker Attribute carrying the unresolvable cast.
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
final class ImplicitRecord extends AbstractModel
{
    /**
     * Empty table name, so the derived one is used instead.
     */
    protected string $table = '';

    /**
     * Cast definition that cannot be resolved into any cast handler.
     *
     * @var array<string, mixed>
     */
    protected array $casts = [
        'marker' => 42,
    ];
}
