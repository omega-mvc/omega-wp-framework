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
use Omega\Database\ORM\Relations\HasMany;
use Omega\Database\ORM\Relations\HasOne;

/**
 * Model relying on the table name derived from its class name.
 *
 * @property mixed $id               Primary key resolved through the magic accessor.
 * @property mixed $body             Body attribute handled by the mass assignment list.
 * @property mixed $title            Title attribute handled by the mass assignment list.
 * @property mixed $views            Integer cast attribute.
 * @property mixed $fillable         Protected property read through the magic accessor.
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
final class Article extends AbstractModel
{
    /**
     * Attributes accepted by fill().
     *
     * @var array<int, string>
     */
    protected array $fillable = ['title', 'body'];

    /**
     * Casts covering every supported short name.
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'live'    => 'bool',
        'meta'    => 'array',
        'price'   => 'money',
        'views'   => 'int',
        'rating'  => 'real',
        'title'   => 'string',
        'raw'     => 'json',
        'custom'  => 'missing-cast-class',
    ];

    /**
     * One to many relationship used by the relation query helpers.
     *
     * @return HasMany The configured one-to-many relationship instance.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TimestampedNote::class);
    }

    /**
     * One to one relationship used by the relation query helpers.
     *
     * @return HasOne The configured one-to-one relationship instance.
     */
    public function widget(): HasOne
    {
        return $this->hasOne(AccessoredWidget::class);
    }
}
