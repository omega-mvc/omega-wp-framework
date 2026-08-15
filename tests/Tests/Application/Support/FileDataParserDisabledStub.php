<?php

/**
 * Part of Omega - Tests Application Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Application\Support;

use Omega\Application\ApplicationPlugin;

/**
 * ApplicationPlugin stub with the WordPress plugin-data parser disabled.
 *
 * Forces ApplicationPlugin::getHeaderField() down the environment-bootstrap
 * branch so the WordPressEnvironmentException guard can be exercised in a
 * plain PHPUnit process (the global get_file_data stub is always loaded).
 *
 * @category  Tests
 * @package   Application
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
final class FileDataParserDisabledStub extends ApplicationPlugin
{
    /**
     * {@inheritdoc}
     */
    protected function isFileDataParserLoaded(): bool
    {
        return false;
    }
}
