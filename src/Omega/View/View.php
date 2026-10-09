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
use function function_exists;
use function htmlspecialchars;
use function is_scalar;
use function is_string;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use function str_replace;

use const ENT_QUOTES;
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
     * A view may declare a parent layout by providing a string `layout` key in
     * its data. The child is rendered first, then the layout is rendered with
     * the child output exposed as the `$content` variable and the remaining
     * data still available, which gives templates a single inheritance step
     * without repeating the page skeleton.
     *
     * The template is evaluated inside a static closure rather than directly in
     * this method, so that the locals of render() stay invisible to it. Without
     * that isolation a template also sees $view, $viewPath and the application
     * through $this, and can leak them into its own output by mistake; here the
     * extracted data, the `$e` escaping helper and the closure parameters are
     * the only state it reads.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $layout = $data['layout'] ?? null;
        unset($data['layout']);

        $content = $this->renderFile($view, $data);

        if (!is_string($layout) || $layout === '') {
            return $content;
        }

        $data['content'] = $content;

        return $this->renderFile($layout, $data);
    }

    /**
     * Render a single view file inside an isolated scope.
     *
     * Templates receive the extracted data plus an `$e()` helper that escapes
     * a scalar for HTML output, so the safety of an echo no longer depends on
     * the template remembering the right WordPress function.
     *
     * @param string $view Logical view name to resolve and include.
     * @param array<string, mixed> $data Variables to expose to the template.
     * @return string The rendered output.
     * @throws ViewFileNotFoundException When the resolved view file does not exist.
     */
    private function renderFile(string $view, array $data): string
    {
        $viewPath = $this->getViewPath($view);

        if (!file_exists($viewPath)) {
            throw new ViewFileNotFoundException($view);
        }

        $template = static function (string $__path, array $__variables): void {
            // Escaping helper exposed to every template as $e().
            $e = static function (mixed $value): string {
                if (!is_scalar($value) && $value !== null) {
                    return '';
                }

                $text = (string) $value;

                return function_exists('esc_html')
                    ? esc_html($text)
                    : htmlspecialchars($text, ENT_QUOTES);
            };

            // EXTR_SKIP stops a data key from hijacking the path or the helper.
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
