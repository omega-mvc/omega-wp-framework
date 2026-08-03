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

use Omega\Application\ApplicationInterface;
use Omega\View\Exception\ViewFileNotFoundException;
use Throwable;

use function extract;
use function file_exists;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use function str_replace;

use const EXTR_SKIP;

/**
 * Render application view files.
 *
 * This class provides a minimal view rendering layer for the framework.
 * It resolves view names expressed in dot notation into physical file paths
 * inside the application's view directory and injects data into those files
 * before including them.
 *
 * Example:
 *
 * "users.profile" becomes:
 * resources/views/users/profile.php
 *
 * The renderer depends on the current application instance in order
 * to determine the framework base path and locate the correct
 * resources' directory.
 *
 * @category  Omega
 * @package   View
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class View implements ViewInterface
{
	#region Lyfecycle
    /**
     * Create a new view renderer instance.
     *
     * @param ApplicationInterface $app The current application container instance.
     */
    public function __construct(protected ApplicationInterface $app)
    {
    }
	#endregion

	#region Rendering
    /**
     * {@inheritdoc}
     */
	public function render(string $view, array $data = []): string
	{
		$viewPath = $this->getViewPath($view);

		if (!file_exists($viewPath)) {
			throw new ViewFileNotFoundException($view);
		}

		ob_start();

		try {
			extract($data, EXTR_SKIP);
			include $viewPath;
		} catch ( Throwable $e) {
			ob_end_clean();
			throw $e;
		}

		return (string) ob_get_clean();
	}
	#endregion

	#region Resolution
    /**
     * Resolve the absolute path of a view file.
     *
     * Dot notation segments are converted into directory separators
     * so the framework can locate nested view files inside the
     * resources/views directory.
     *
     * Example:
     *
     * "blog.post" becomes:
     * /resources/views/blog/post.php
     *
     * @param string $view The logical view name.
     * @return string The absolute path to the resolved view file.
     */
    protected function getViewPath(string $view): string
    {
        $view = str_replace('.', '/', $view);

        return $this->app->getBasePath() . "/resources/views/$view.php";
    }
	#endregion
}
