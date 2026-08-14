<?php

/**
 * Part of Omega - Tests Validator Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

declare(strict_types=1);

namespace Tests\Validator;

use Omega\Validator\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function var_export;

/**
 * Tests the Validator rule engine.
 *
 * @category  Tests
 * @package   Validator
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2025 - 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    /**
     * Test the factory method returns a validator instance.
     */
    public function testMakeReturnsValidatorInstance(): void
    {
        $this->assertInstanceOf(Validator::class, Validator::make([], []));
    }

    /**
     * Test valid data passes and is returned by validated().
     */
    public function testValidatesValidDataAndReturnsValidatedSubset(): void
    {
        $validator = Validator::make(
            ['name' => 'Ada', 'email' => 'ada@example.com'],
            ['name' => 'required|string', 'email' => 'required|email']
        );
        $validator->validate();

        $this->assertFalse($validator->fails());
        $this->assertSame(['name' => 'Ada', 'email' => 'ada@example.com'], $validator->validated());
    }

    /**
     * Test the required rule fails for a missing field.
     */
    public function testRequiredFailsWhenFieldIsMissing(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $validator->validate();

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->hasErrors());
        $this->assertSame(['name' => 'The field name is required.'], $validator->errors());
        $this->assertSame([], $validator->validated());
    }

    /**
     * Test the required rule fails for empty or null values.
     */
    public function testRequiredFailsWhenFieldIsEmptyOrNull(): void
    {
        foreach (['', null] as $value) {
            $validator = Validator::make(['name' => $value], ['name' => 'required']);
            $validator->validate();
            $this->assertTrue($validator->fails());
        }
    }

    /**
     * Test the required rule accepts falsy but non-empty values.
     */
    public function testRequiredAcceptsFalsyButNonEmptyValues(): void
    {
        foreach ([0, false, [], '0'] as $value) {
            $validator = Validator::make(['name' => $value], ['name' => 'required']);
            $validator->validate();
            $this->assertFalse($validator->fails(), var_export($value, true) . ' should pass required.');
        }
    }

    /**
     * Test the email rule accepts a valid address.
     */
    public function testEmailAcceptsValidAddress(): void
    {
        $validator = Validator::make(['email' => 'user@example.com'], ['email' => 'email']);
        $validator->validate();

        $this->assertFalse($validator->fails());
    }

    /**
     * Test the email rule rejects an invalid address.
     */
    public function testEmailRejectsInvalidAddress(): void
    {
        $validator = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
        $validator->validate();

        $this->assertTrue($validator->fails());
        $this->assertSame(
            ['email' => 'The field email must be a valid email address.'],
            $validator->errors()
        );
    }

    /**
     * Test the email rule ignores null values.
     */
    public function testEmailRuleIgnoresNullValue(): void
    {
        $validator = Validator::make(['email' => null], ['email' => 'email']);
        $validator->validate();

        $this->assertFalse($validator->fails());
    }

    /**
     * Test the array rule accepts arrays and rejects scalars.
     */
    public function testArrayRule(): void
    {
        $validator = Validator::make(['tags' => ['a']], ['tags' => 'array']);
        $validator->validate();
        $this->assertFalse($validator->fails());

        $validator = Validator::make(['tags' => 'a'], ['tags' => 'array']);
        $validator->validate();
        $this->assertTrue($validator->fails());
        $this->assertSame(['tags' => 'The field tags must be an array.'], $validator->errors());
    }

    /**
     * Test the min rule enforces a lower length boundary.
     */
    public function testMinLength(): void
    {
        $validator = Validator::make(['name' => 'ab'], ['name' => 'min:3']);
        $validator->validate();
        $this->assertTrue($validator->fails());

        $validator = Validator::make(['name' => 'abc'], ['name' => 'min:3']);
        $validator->validate();
        $this->assertFalse($validator->fails());
    }

    /**
     * Test the max rule enforces an upper length boundary.
     */
    public function testMaxLength(): void
    {
        $validator = Validator::make(['name' => '123456'], ['name' => 'max:5']);
        $validator->validate();
        $this->assertTrue($validator->fails());

        $validator = Validator::make(['name' => '12345'], ['name' => 'max:5']);
        $validator->validate();
        $this->assertFalse($validator->fails());
    }

    /**
     * Test the size rule enforces an exact length.
     */
    public function testSizeEnforcesExactLength(): void
    {
        $validator = Validator::make(['code' => 'ab'], ['code' => 'size:3']);
        $validator->validate();
        $this->assertTrue($validator->fails());

        $validator = Validator::make(['code' => 'abc'], ['code' => 'size:3']);
        $validator->validate();
        $this->assertFalse($validator->fails());
        $this->assertSame(['code' => 'abc'], $validator->validated());
    }

    /**
     * Test the integer rule accepts integers and rejects other values.
     */
    public function testIntegerRule(): void
    {
        $validator = Validator::make(['age' => '42'], ['age' => 'integer']);
        $validator->validate();
        $this->assertFalse($validator->fails());

        foreach (['42.5', 'abc'] as $value) {
            $validator = Validator::make(['age' => $value], ['age' => 'integer']);
            $validator->validate();
            $this->assertTrue($validator->fails());
        }
    }

    /**
     * Test the integer rule rejects the string "0".
     *
     * FILTER_VALIDATE_INT returns 0, which is falsy, so the validation
     * fails. This documents the current quirk of the implementation.
     */
    public function testIntegerRejectsZero(): void
    {
        $validator = Validator::make(['age' => '0'], ['age' => 'integer']);
        $validator->validate();

        $this->assertTrue($validator->fails());
    }

    /**
     * Test the numeric rule accepts numbers and rejects non-numeric values.
     */
    public function testNumericRule(): void
    {
        $validator = Validator::make(['price' => '12.5'], ['price' => 'numeric']);
        $validator->validate();
        $this->assertFalse($validator->fails());

        $validator = Validator::make(['price' => 'abc'], ['price' => 'numeric']);
        $validator->validate();
        $this->assertTrue($validator->fails());
    }

    /**
     * Test the string rule accepts strings and rejects other types.
     */
    public function testStringRule(): void
    {
        $validator = Validator::make(['name' => 'Ada'], ['name' => 'string']);
        $validator->validate();
        $this->assertFalse($validator->fails());

        $validator = Validator::make(['name' => 42], ['name' => 'string']);
        $validator->validate();
        $this->assertTrue($validator->fails());
    }

    /**
     * Test the date rule accepts valid dates and rejects invalid ones.
     */
    public function testDateRule(): void
    {
        $validator = Validator::make(['birth' => '2024-01-15'], ['birth' => 'date']);
        $validator->validate();
        $this->assertFalse($validator->fails());

        $validator = Validator::make(['birth' => 'not-a-date'], ['birth' => 'date']);
        $validator->validate();
        $this->assertTrue($validator->fails());
    }

    /**
     * Test the in rule matches allowed values strictly.
     */
    public function testInRuleWithStrictMatching(): void
    {
        $validator = Validator::make(['role' => 'admin'], ['role' => 'in:admin,user,guest']);
        $validator->validate();
        $this->assertFalse($validator->fails());

        $validator = Validator::make(['role' => 'root'], ['role' => 'in:admin,user,guest']);
        $validator->validate();
        $this->assertTrue($validator->fails());
        $this->assertSame(
            ['role' => 'The field role must be one of: admin, user, guest.'],
            $validator->errors()
        );
    }

    /**
     * Test the nullable rule skips validation when the value is empty.
     */
    public function testNullableSkipsValidationWhenValueIsEmpty(): void
    {
        foreach (['', null] as $value) {
            $validator = Validator::make(['phone' => $value], ['phone' => 'nullable|email']);
            $validator->validate();
            $this->assertFalse($validator->fails());
        }
    }

    /**
     * Test the nullable rule still validates present values.
     */
    public function testNullableStillValidatesPresentValue(): void
    {
        $validator = Validator::make(['phone' => 'not-an-email'], ['phone' => 'nullable|email']);
        $validator->validate();

        $this->assertTrue($validator->fails());
    }

    /**
     * Test unknown rules are silently ignored.
     */
    public function testUnknownRuleIsIgnored(): void
    {
        $validator = Validator::make(['name' => 'Ada'], ['name' => 'unknown_rule']);
        $validator->validate();

        $this->assertFalse($validator->fails());
        $this->assertSame(['name' => 'Ada'], $validator->validated());
    }

    /**
     * Test multiple rules are evaluated sequentially.
     */
    public function testMultipleRulesEvaluatedSequentially(): void
    {
        $validator = Validator::make(['name' => 'ab'], ['name' => 'required|string|min:3']);
        $validator->validate();

        $this->assertTrue($validator->fails());
    }

    /**
     * Test a failing field is excluded from the validated dataset.
     */
    public function testFailingRuleExcludesFieldFromValidatedData(): void
    {
        $validator = Validator::make(
            ['name' => 'Ada', 'email' => 'not-an-email'],
            ['name' => 'required', 'email' => 'email']
        );
        $validator->validate();

        $this->assertSame(['name' => 'Ada'], $validator->validated());
    }

    /**
     * Test validated() resolves a single field using dot notation.
     */
    public function testValidatedWithDotNotationKey(): void
    {
        $validator = Validator::make(['user' => ['name' => 'Ada']], ['user.name' => 'required']);
        $validator->validate();

        $this->assertSame('Ada', $validator->validated('user.name'));
    }

    /**
     * Test nested data is validated and preserved through dot notation.
     */
    public function testNestedDataValidation(): void
    {
        $validator = Validator::make(
            ['user' => ['name' => 'Ada', 'age' => 30]],
            ['user.name' => 'required|string', 'user.age' => 'integer']
        );
        $validator->validate();

        $this->assertFalse($validator->fails());
        $this->assertSame(['user' => ['name' => 'Ada', 'age' => 30]], $validator->validated());
    }

    /**
     * Test a missing nested field produces a dot-notated error key.
     */
    public function testNestedRequiredError(): void
    {
        $validator = Validator::make(['user' => []], ['user.name' => 'required']);
        $validator->validate();

        $this->assertSame(['user.name' => 'The field user.name is required.'], $validator->errors());
    }

    /**
     * Test get() reads fields with a default fallback.
     */
    public function testGetReturnsFieldWithDefault(): void
    {
        $validator = Validator::make(['name' => 'Ada'], []);

        $this->assertSame('Ada', $validator->get('name'));
        $this->assertSame('fallback', $validator->get('missing', 'fallback'));
    }

    /**
     * Test set() writes nested values and getAll() reflects them.
     */
    public function testSetAndGetAll(): void
    {
        $validator = Validator::make([], []);
        $validator->set('profile.city', 'Terni');

        $this->assertSame('Terni', $validator->get('profile.city'));
        $this->assertSame(['profile' => ['city' => 'Terni']], $validator->getAll());
    }

    /**
     * Test has() checks nested key existence.
     */
    public function testHasChecksNestedKeyExistence(): void
    {
        $validator = Validator::make(['profile' => ['city' => 'Terni']], []);

        $this->assertTrue($validator->has('profile.city'));
        $this->assertFalse($validator->has('profile.country'));
    }

    /**
     * Test the magic accessors delegate to dot notation.
     */
    public function testMagicAccessors(): void
    {
        $validator = Validator::make(['user' => ['name' => 'Ada']], []);

        $this->assertSame('Ada', $validator->{'user.name'});
        $this->assertTrue(isset($validator->{'user.name'}));
        $this->assertFalse(isset($validator->{'user.missing'}));

        $validator->{'user.city'} = 'Terni';
        $this->assertSame('Terni', $validator->get('user.city'));
    }
}
