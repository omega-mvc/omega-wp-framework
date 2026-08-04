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
 * Defines a console command using PHP attributes.
 *
 * This attribute provides a declarative way to configure a command,
 * including its name, description, aliases, visibility, arguments,
 * and options. It is processed at runtime via reflection.
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
class AsCommand
{
	/**
	 * Creates a new command metadata attribute.
	 *
	 * Defines the command name, description, arguments, options, aliases,
	 * and visibility. This metadata is read by the console command loader
	 * to configure Symfony Console commands automatically.
	 *
	 * @param string      $name        Command name used to invoke it (e.g. 'app:user-clean').
	 * @param string|null $description Short description displayed in the command list.
	 * @param array       $arguments   Command argument definitions in the format:
	 *                                 [name => [mode, description, default?]].
	 * @param array       $options     Command option definitions in the format:
	 *                                 [name => [shortcut, mode, description, default?, suggestedValues?]].
	 * @param array       $aliases     Alternative names that can also invoke the command.
	 * @param bool        $hidden      Whether the command should be hidden from the command list.
	 */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $arguments = [],
        public array $options = [],
        public array $aliases = [],
        public bool $hidden = false,
    ) {}
}
