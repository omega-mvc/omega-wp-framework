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

namespace Omega\Http\Facade;

use Omega\Facade\AbstractFacade;
use Omega\Http\Response as HttpResponse;
use WP_Error;
use WP_REST_Response;

/**
 * Facade providing a static interface to the HTTP Response service.
 *
 * This facade acts as a lightweight abstraction over the underlying HTTP response handler,
 * allowing developers to return structured API responses in a consistent and framework-oriented way.
 *
 * It is primarily designed for REST contexts (such as WordPress REST API integration),
 * where responses must conform to WP_REST_Response or WP_Error structures depending on status.
 *
 * The facade simplifies response creation by exposing a single entry point for JSON responses,
 * automatically handling status codes, headers, and error transformation logic.
 *
 * @category   Omega
 * @package    Http
 * @subpackage Facade
 * @link       https://omega-mvc.github.io
 * @author     Adriano Giovannini <agisoftt@gmail.com>
 * @copyright  Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version    1.0.0
 *
 * phpcs:ignore
 * @method static WP_Error|WP_REST_Response json(array<int|string, mixed> $data = [], int $status = 200, array<string, string> $headers = [], int $options = 0)
 */
class Response extends AbstractFacade
{
    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor(): string
    {
        return HttpResponse::class;
    }
}
