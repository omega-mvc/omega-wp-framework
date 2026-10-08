<?php

/**
 * Part of Omega - Http Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Omega\Http;

/**
 * HtmlResponse
 *
 * Marks a controller response as intentionally raw HTML.
 *
 * The admin/web dispatchers escape plain string results by default (see
 * OMP-06). Controllers that render trusted templates — such as view output —
 * can return an HtmlResponse to opt into unescaped output explicitly.
 *
 * @category   Omega
 * @package    Http
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 */
final class HtmlResponse
{
    #region Properties
    /** @var string Raw HTML payload. */
    private string $content;
    #endregion

    #region Lifecycle
    /**
     * HtmlResponse constructor.
     *
     * @param string $content Raw HTML content to render as-is.
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }
    #endregion

    #region Accessor
    /**
     * Retrieve the raw HTML payload.
     *
     * @return string The HTML content.
     */
    public function content(): string
    {
        return $this->content;
    }
    #endregion
}
