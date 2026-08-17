<?php

/**
 * Part of Omega - Tests Http Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Http;

use Omega\Http\Response as HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Routing\Support\WPError;
use Tests\Routing\Support\WPRestResponse;
use Tests\Routing\WordPressRuntime;

/**
 * Tests the Http Response builder.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(HttpResponse::class)]
final class ResponseTest extends TestCase
{
    /**
     * Test error statuses are converted into a WP_Error.
     */
    public function testReturnsErrorResponseForBadStatus(): void
    {
        $result = (new HttpResponse())->json(['message' => 'Bad request.'], 400);

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertSame(400, $result->get_error_code());
        $this->assertSame('Bad request.', $result->getErrorMessage());
        $this->assertSame(['status' => 400], $result->get_error_data());
    }

    /**
     * Test the error key is used when no message is present.
     */
    public function testFallsBackToErrorKeyWhenMessageIsMissing(): void
    {
        $result = (new HttpResponse())->json(['error' => 'Boom.'], 404);

        $this->assertSame('Boom.', $result->getErrorMessage());
    }

    /**
     * Test a generic message is used when no error detail is given.
     */
    public function testUsesGenericMessageWhenNoErrorDetailIsGiven(): void
    {
        $result = (new HttpResponse())->json([], 500);

        $this->assertSame('Error', $result->getErrorMessage());
    }

    /**
     * Test successful statuses produce a REST response with the payload.
     */
    public function testReturnsRestResponseForSuccessfulStatus(): void
    {
        $result = (new HttpResponse())->json(['ok' => true], 200);

        $this->assertInstanceOf(WPRestResponse::class, $result);
        $this->assertSame(['ok' => true], $result->get_data());
        $this->assertSame(200, $result->get_status());
    }

    /**
     * Test custom status codes and headers are applied to the response.
     */
    public function testAppliesCustomStatusAndHeaders(): void
    {
        $result = (new HttpResponse())->json(['id' => 7], 201, ['X-Rate-Limit' => '100']);

        $this->assertSame(201, $result->get_status());
        $this->assertSame(['X-Rate-Limit' => '100'], $result->get_headers());
    }

    /**
     * Test the WP_Error path when rest_ensure_response itself fails.
     */
    public function testReturnsWpErrorWhenRestEnsureResponseFails(): void
    {
        WordPressRuntime::$forceRestError = true;

        $result = (new HttpResponse())->json(['ok' => true], 200);

        $this->assertInstanceOf(WPError::class, $result);
        $this->assertSame('rest_error', $result->get_error_code());

        WordPressRuntime::$forceRestError = false;
    }
}
