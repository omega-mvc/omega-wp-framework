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

        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
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
        $form = new FormRequest(new WPRestRequest(['q' => 'hello', 'page' => 2]));

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

        $form = new FormRequest(new WPRestRequest());

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
            new WPRestRequest(['name' => 'Ada', 'email' => 'ada@example.com'])
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
        $form = new ContactFormRequest(new WPRestRequest(['name' => '', 'email' => 'nope']));
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
        $form = new MergingFormRequest(new WPRestRequest([]));
        $form->validate();

        $this->assertFalse($form->fails());
        $this->assertSame('default', $form->validated('locale'));
    }
}
