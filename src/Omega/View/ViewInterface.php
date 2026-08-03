<?php

/**
 * Part of Omega - View Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\View;

use Omega\View\Exception\ViewFileNotFoundException;

/**
 * Contract for application view rendering services.
 *
 * Implementations of this interface are responsible for transforming
 * logical view identifiers into rendered output strings.
 *
 * A view identifier may use dot notation to represent nested view paths,
 * allowing implementations to resolve structured templates inside the
 * application's view directory.
 *
 * The interface defines the minimum contract required by the framework
 * to render templates while keeping the rendering engine independent
 * from the underlying implementation details.
 *
 * Example:
 *
 * render('admin.dashboard', ['title' => 'Dashboard']);
 *
 * Resolves the logical view:
 *
 * resources/views/admin/dashboard.php
 *
 * @category  Omega
 * @package   View
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
interface ViewInterface
{
	#region Rendering
	/**
	 * Render a view file.
	 *
	 * The provided view name may use dot notation to represent
	 * nested directories. The supplied data array is extracted
	 * into individual variables so they become directly available
	 * inside the included template.
	 *
	 * Example:
	 *
	 * make('admin.dashboard', ['title' => 'Dashboard']);
	 *
	 * makes the variable $title available inside:
	 *
	 * resources/views/admin/dashboard.php
	 *
	 * @param string $view The logical name of the view using dot notation.
	 * @param array $data The data to expose to the view file.
	 * @return string Return the template string.
	 * @throws ViewFileNotFoundException Thrown when the resolved view file does not exist.
	 */
	public function render(string $view, array $data = []): string;
	#endregion
}
