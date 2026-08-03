<?php

/**
 * Part of Omega - Database Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Database;

use Omega\Database\ORM\AbstractModel;
use ReflectionException;

/**
 * DynamicModel
 *
 * Lightweight runtime model implementation used for representing
 * database records when the concrete model class is not required.
 *
 * This model is typically used by dynamic query builders or relation
 * resolvers that need to hydrate results without binding to a specific
 * domain model class.
 *
 * It extends AbstractModel and provides a fixed primary key definition
 * along with a simple table resolution mechanism based on constructor input.
 *
 * @category  Omega
 * @package   Database
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class DynamicModel extends AbstractModel
{
	#region Properties
    /** @var string Primary key of the underlying table. */
    protected string $primaryKey = 'id';
	#endregion

	#region Lifecycle
    /**
     * DynamicModel constructor.
     *
     * Initializes the model with raw data and the associated table name.
     *
     * @param array<string, mixed>|object $data Raw record data used to hydrate the model.
     * @param string $table Table name associated with this dynamic model.
     * @throws ReflectionException If the parent model fails to resolve property or schema metadata via reflection.
     */
    public function __construct($data, $table)
    {
        parent::__construct($data, $table);
    }
	#endregion

	#region Lifecycle
    /**
     * Get the table name associated with this model instance.
     *
     * Overrides AbstractModel behavior to return the dynamically assigned table.
     *
     * @return string Table name used for database operations.
     */
    public function getTableName(): string
    {
        return $this->table;
    }
	#endregion
}
