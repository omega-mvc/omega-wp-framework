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
     *
     * The template is evaluated inside a static closure rather than directly in
     * this method, so that the locals of render() stay invisible to it. Without
     * that isolation a template also sees $view, $viewPath and the application
     * through $this, and can leak them into its own output by mistake; here the
     * extracted data and the closure parameters are the only state it reads.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $viewPath = $this->getViewPath($view);

        if (!file_exists($viewPath)) {
            throw new ViewFileNotFoundException($view);
        }

        $template = static function (string $__path, array $__variables): void {
            // EXTR_SKIP stops a data key from hijacking the path being included.
            extract($__variables, EXTR_SKIP);

            include $__path;
        };

        ob_start();

        try {
            $template($viewPath, $data);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }
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
