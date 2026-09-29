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

use Omega\Http\FormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Http\Support\ContactFormRequest;
use Tests\Http\Support\MergingFormRequest;
use Tests\Routing\Support\WPRestRequest;
use WP_REST_Request;

use function is_string;

/**
 * Tests the FormRequest adapter over the Validator engine.
 *
 * @category  Tests
 * @package   Http
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(FormRequest::class)]
final class FormRequestTest extends TestCase
{
    /**
     * Original REQUEST_METHOD value to restore after each test.
     */
    private ?string $requestMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        $this->requestMethod = is_string($method) ? $method : null;
    }

    /**
     * Builds a request double typed as the real WordPress request class.
     *
     * tests/bootstrap.php aliases WP_REST_Request to
     * Tests\Routing\Support\WPRestRequest, so the instance below *is* a
     * WP_REST_Request at runtime. The assertion pins that contract down and
     * keeps the static type of the returned double correct.
     *
     * @param array<string, mixed> $params Request parameters.
     * @return WP_REST_Request The aliased request double.
     */
    private function makeRequest(array $params = []): WP_REST_Request
    {
        $request = new WPRestRequest($params);

        $this->assertInstanceOf(WP_REST_Request::class, $request);

        return $request;
    }

    protected function tearDown(): void
    {
        if ($this->requestMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->requestMethod;
        }

        parent::tearDown();
    }

    /**
     * Test the constructor extracts request parameters into the dataset.
     */
    public function testConstructorExtractsRequestParams(): void
    {
        $form = new FormRequest($this->makeRequest(['q' => 'hello', 'page' => 2]));

        $this->assertSame('hello', $form->get('q'));
        $this->assertSame(2, $form->get('page'));
        $this->assertSame(['q' => 'hello', 'page' => 2], $form->getAll());
    }

    /**
     * Test isMethod() performs a case-insensitive comparison.
     */
    public function testIsMethodIsCaseInsensitive(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $form = new FormRequest($this->makeRequest());

        $this->assertTrue($form->isMethod('POST'));
        $this->assertTrue($form->isMethod('post'));
        $this->assertFalse($form->isMethod('GET'));
    }

    /**
     * Test request data is validated against subclass-defined rules.
     */
    public function testValidatesRequestDataUsingSubclassRules(): void
    {
        $form = new ContactFormRequest(
            $this->makeRequest(['name' => 'Ada', 'email' => 'ada@example.com'])
        );
        $form->validate();

        $this->assertFalse($form->fails());
        $this->assertSame(['name' => 'Ada', 'email' => 'ada@example.com'], $form->validated());
    }

    /**
     * Test invalid request data produces field errors.
     */
    public function testFailsValidationWithInvalidRequestData(): void
    {
        $form = new ContactFormRequest($this->makeRequest(['name' => '', 'email' => 'nope']));
        $form->validate();

        $this->assertTrue($form->fails());
        $this->assertArrayHasKey('name', $form->errors());
        $this->assertArrayHasKey('email', $form->errors());
    }

    /**
     * Test prepareForValidation() can seed defaults before validation.
     */
    public function testPrepareForValidationMergesDefaults(): void
    {
        $form = new MergingFormRequest($this->makeRequest());
        $form->validate();

        $this->assertFalse($form->fails());
        $this->assertSame('default', $form->validated('locale'));
    }
}
