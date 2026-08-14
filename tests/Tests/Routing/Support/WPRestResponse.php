<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

/**
 * Minimal WP_REST_Response replacement.
 *
 * @category  Stubs
 * @package   Routing
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class WPRestResponse
{
    /**
     * Response payload.
     *
     * @var mixed
     */
    public mixed $data = [];

    /**
     * HTTP status code.
     */
    public int $status = 200;

    /**
     * Response headers indexed by header name.
     *
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * @param mixed                  $data    Response payload
     * @param int                    $status  HTTP status code
     * @param array<string, string>  $headers Response headers
     */
    public function __construct(mixed $data = [], int $status = 200, array $headers = [])
    {
        $this->data = $data;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function set_status(int $status): void
    {
        $this->status = $status;
    }

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function get_data(): mixed
    {
        return $this->data;
    }

    public function get_status(): int
    {
        return $this->status;
    }

    public function get_headers(): array
    {
        return $this->headers;
    }
}
