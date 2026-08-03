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

use WP_Error;
use WP_REST_Response;

use function is_wp_error;
use function rest_ensure_response;

/**
 * Response
 *
 * Lightweight HTTP response builder for WordPress REST API layer.
 *
 * This class provides a simplified interface for generating JSON responses
 * and error responses compatible with WP_REST_Response and WP_Error.
 *
 * It acts as a thin abstraction over WordPress REST helpers, standardizing
 * how API responses are created inside the Omega HTTP layer.
 *
 * The design favors simplicity and direct mapping to WordPress internals,
 * without introducing a full HTTP abstraction layer.
 *
 * @category  Omega
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Response
{
	#region Lifecycle
    /**
     * Create a JSON REST response or error response.
     *
     * Builds a WP_REST_Response for successful requests or a WP_Error
     * instance for error states (status >= 400). Supports custom headers
     * and status codes.
     *
     * If the response creation fails, the original WP_Error is returned.
     *
     * @param array $data Response payload data.
     * @param int $status HTTP status code.
     * @param array $headers Optional response headers.
     * @param int $options Reserved for future JSON encoding options.
     * @return WP_Error|WP_REST_Response REST-compatible response object.
     */
    public function json(
        array $data = [],
        int $status = 200,
        array $headers = [],
        int $options = 0
    ): WP_Error|WP_REST_Response {
        if ($status >= 400) {
            return new WP_Error(
                $status,
                $data['message'] ?? ($data['error'] ?? 'Error'),
                ['status' => $status]
            );
        }

        $response = rest_ensure_response($data);

        if (is_wp_error($response)) {
            return $response;
        }

        $response->set_status($status);

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }
	#endregion
}
