<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

/**
 * Minimal WP_Error replacement.
 *
 * @category  Stubs
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class WPError
{
    /**
     * Stored error message.
     */
    private string $message;

    /**
     * @param string $code    Error code
     * @param mixed  $message Error message
     * @param mixed  $data    Optional error data
     */
    public function __construct(string $code = '', mixed $message = '', mixed $data = '')
    {
        $this->message = (string) $message;
    }

    /**
     * @return string The stored error message
     */
    public function getErrorMessage(): string
    {
        return $this->message;
    }
}
