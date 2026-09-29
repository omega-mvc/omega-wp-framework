<?php

declare(strict_types=1);

/**
 * Part of Omega - Tests\Console\Commands\Fixtures.
 *
 * @link      https://omega-mvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Axel Magold (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   2.0.0
 */

use Omega\Database\Schema\Table\Create;
use Omega\Database\Schema\Table\Drop;
use Tests\Console\Commands\Fixtures\ScriptedSchemaConnection;

return [
    'up' => [
        new Create('omega', 'users', ScriptedSchemaConnection::shared('up')),
    ],
    'down' => [
        new Drop('omega', 'users', ScriptedSchemaConnection::shared('down')),
    ],
];