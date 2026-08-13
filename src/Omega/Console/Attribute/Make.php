<?php

/**
 * Part of Omega - Console Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Console\Attribute;

use Attribute;

/**
 * Defines metadata for "make" style commands that generate files.
 *
 * This attribute describes how a file should be generated, including
 * the template to use, destination path, naming conventions, and
 * custom messages for success or warning states.
 *
 * @category   Omega
 * @package    Console
 * @subpackage Attribute
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Make
{
    /**
     * Creates a new file generation metadata attribute.
     *
     * Defines the template, destination, naming conventions, and placeholder
     * mappings used by the make command to generate framework artifacts such
     * as controllers, models, commands, or service providers.
     *
     * @param string $template Path to the stub/template file.
     * @param string $path     Container binding or configuration key resolving the base output directory.
     * @param string $pattern  Placeholder within the template that will be
     *                         replaced with the generated class name.
     * @param string $suffix   Suffix appended to the generated file name.
     * @param string $target   Logical target used for display messages and destination resolution.
     * @param string $info     Success message displayed when generation completes.
     * @param string $warning  Warning message displayed when the target file already exists.
     * @param array  $vars     Additional template placeholder mappings in the
     *                         format [placeholder => transformation].
     */
    public function __construct(
        public string $template,
        public string $path,
        public string $pattern,
        public string $suffix,
        public string $target,
        public string $info,
        public string $warning,
        public array $vars = []
    ) {
    }
}
