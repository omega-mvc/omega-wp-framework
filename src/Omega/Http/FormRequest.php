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

use Omega\Validator\Validator;
use WP_REST_Request;

use function strtolower;

/**
 * FormRequest
 *
 * Adapter layer between WP_REST_Request and the validation system.
 *
 * This class transforms an incoming WordPress REST request into a validation-ready
 * dataset and extends the base Validator to allow rule execution on HTTP input data.
 *
 * It acts as the entry point for request validation in controller or route contexts,
 * providing a clean separation between transport layer (HTTP) and validation logic.
 *
 * @category  Omega
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class FormRequest extends Validator
{
	#region Lifecycle
    /**
     * Create a new FormRequest instance from a WordPress REST request.
     *
     * Extracts request parameters and initializes the underlying validation
     * dataset. Rules are intentionally left empty at construction time and
     * are expected to be defined or resolved separately.
     *
     * @param WP_REST_Request $request Incoming REST request instance.
     */
    public function __construct(WP_REST_Request $request)
    {
        parent::__construct($request->get_params(), []);

        $this->data = $request->get_params();
    }
	#endregion

	#region Request
    /**
     * Determine if the current HTTP request matches the given method.
     *
     * Performs a case-insensitive comparison against the server request method.
     *
     * @param string $method HTTP method to check (GET, POST, PUT, DELETE, etc.).
     * @return bool True if the request method matches, otherwise false.
     */
    public function isMethod(string $method): bool
    {
        return strtolower($method) === strtolower($_SERVER['REQUEST_METHOD']);
    }
	#endregion
}
