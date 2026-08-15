<?php

/**
 * Part of Omega - Validator Package.
 *
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */

/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */

declare(strict_types=1);

namespace Omega\Validator;

use Omega\Str\Str;

use function array_key_exists;
use function call_user_func_array;
use function count;
use function explode;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function method_exists;
use function str_contains;
use function strlen;
use function strtotime;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_INT;

/**
 * Data validation engine for array-based input structures.
 *
 * This class provides a rule-based validation system designed to
 * validate raw input data against a set of declarative rules.
 * It supports dot notation for nested arrays, enabling validation
 * of deeply structured data without requiring manual traversal.
 *
 * The validator operates in two phases:
 *
 * 1. Rule parsing and evaluation
 * 2. Data extraction and validation execution
 *
 * Only fields that pass all validation rules are included in the
 * final validated dataset.
 *
 * ------------------------------------------------------------
 * RULE SYNTAX
 * ------------------------------------------------------------
 *
 * Validation rules are defined using pipe-separated strings:
 *
 *     'name'  => 'required|string|min:3|max:50'
 *     'email' => 'required|email'
 *
 * Rules with parameters use colon notation:
 *
 *     'min:3'
 *     'max:255'
 *     'in:admin,user,guest'
 *
 * Multiple rules are evaluated sequentially.
 *
 * ------------------------------------------------------------
 * NESTED DATA SUPPORT
 * ------------------------------------------------------------
 *
 * The validator supports dot notation for nested arrays:
 *
 *     'profile.city' => 'required|string'
 *
 * Example input:
 *
 *     [
 *         'profile' => [
 *             'city' => 'Terni'
 *         ]
 *     ]
 *
 * ------------------------------------------------------------
 * NULLABLE BEHAVIOR
 * ------------------------------------------------------------
 *
 * The "nullable" rule allows fields to be optional.
 * If a field is null or empty, other rules are skipped.
 *
 * ------------------------------------------------------------
 * USAGE EXAMPLES
 * ------------------------------------------------------------
 *
 * Basic usage:
 *
 *     $validator = Validator::make($data, [
 *         'name'  => 'required|string|min:3',
 *         'email' => 'required|email',
 *     ]);
 *
 *     $validator->validate();
 *
 *     if ($validator->fails()) {
 *         print_r($validator->errors());
 *     }
 *
 *     $valid = $validator->validated();
 *
 * Nested data:
 *
 *     $validator = Validator::make($data, [
 *         'user.name'        => 'required|string',
 *         'user.profile.age' => 'integer|min:18',
 *     ]);
 *
 * Conditional nullable field:
 *
 *     $rules = [
 *         'phone' => 'nullable|string|min:10'
 *     ];
 *
 * ------------------------------------------------------------
 * VALIDATION RESULT
 * ------------------------------------------------------------
 *
 * - errors(): returns all validation errors grouped by field
 * - validated(): returns only fields that passed validation
 * - fails(): returns true if validation failed
 *
 * ------------------------------------------------------------
 * NOTE
 * ------------------------------------------------------------
 *
 * This validator does not perform type coercion.
 * All values are validated in their raw form as provided.
 *
 * @category  Omega
 * @package   Validator
 * @link      https://omega-mvc.github.io
 * @author    Adriano Giovannini <agisoftt@gmail.com>
 * @copyright Copyright (c) 2026 Adriano Giovannini (https://omega-mvc.github.io)
 * @license   https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version   1.0.0
 */
class Validator
{
    #region Properties
    /** @var array Validation error messages indexed by field name. */
    protected array $errors = [];

    /** @var array Data that passed all validation rules. */
    protected array $validatedData = [];
    #endregion

    #region Lifecycle
    /**
     * Create a new validator instance.
     *
     * @param array $data Input data to validate
     * @param array $rules Validation rules definition
     * @return void
     */
    public function __construct(protected array $data, protected array $rules)
    {
    }
    #endregion

    #region Factory
    /**
     * Create a new Validator instance.
     *
     * @param mixed $data Input data to validate
     * @param mixed $rules Validation rules definition
     * @return static Validator instance
     */
    public static function make(mixed $data, mixed $rules): static
    {
        return new static($data, $rules);
    }
    #endregion

    #region Validation
    /**
     * Execute validation process using defined rules.
     *
     * @return void
     */
    public function validate(): void
    {
        $this->prepareForValidation();

        $rules               = $this->rules();
        $this->validatedData = [];

        array_walk($rules, function ($rule, $field): void {
            $this->validateField($field, $rule);
        });
    }

    /**
     * Validate a single field against its rule definition.
     *
     * @param string $field Field name using dot notation
     * @param string $rule Pipe-separated list of rules for the field
     * @return void
     */
    private function validateField(string $field, string $rule): void
    {
        $fieldRules  = explode('|', $rule);
        $fieldValid  = true;
        $isNullable  = in_array('nullable', $fieldRules);
        $fieldValue  = Str::getNestedValue($this->data, $field);

        if ($fieldValue === null) {
            if ($isNullable) {
                return;
            }

            $fieldValid = $this->applyFieldRules($field, $fieldRules, $fieldValid);

            if (!$this->hasNestedKey($this->data, $field)) {
                return;
            }
        } else {
            if ($fieldValue === '') {
                if ($isNullable) {
                    return;
                }
            }

            $fieldValid = $this->applyFieldRules($field, $fieldRules, $fieldValid);
        }

        if ($fieldValid) {
            Str::setNestedValue($this->validatedData, $field, $fieldValue);
        }
    }

    /**
     * Apply every rule of a field definition and report whether it stayed valid.
     *
     * @param string $field Field name using dot notation
     * @param string[] $fieldRules Rules to apply
     * @param bool $fieldValid Whether the field is still considered valid
     * @return bool
     */
    private function applyFieldRules(string $field, array $fieldRules, bool $fieldValid): bool
    {
        array_walk($fieldRules, function ($singleRule) use (&$fieldValid, $field): void {
            $this->applySingleRule($field, $singleRule, $fieldValid);
        });

        return $fieldValid;
    }

    /**
     * Apply a single rule of a field rule definition.
     *
     * @param string $field Field name using dot notation
     * @param string $singleRule Single rule with optional colon-separated parameters
     * @param bool $fieldValid Whether the field is still considered valid (modified by reference)
     * @return void
     */
    private function applySingleRule(string $field, string $singleRule, bool &$fieldValid): void
    {
        $ruleParts  = explode(':', $singleRule);
        $method     = $ruleParts[0];
        $parameters = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

        if ($method === 'nullable') {
            return;
        }

        if (!method_exists($this, $method)) {
            return;
        }

        $errorCountBefore = count($this->errors);
        call_user_func_array([$this, $method], [$field, ...$parameters]);
        if (count($this->errors) > $errorCountBefore) {
            $fieldValid = false;
        }
    }

    /**
     * Hook executed before validation starts for data preprocessing.
     */
    protected function prepareForValidation()
    {
        // Prepare the data for validation
    }

    /**
     * Retrieve validation rules used by the validator.
     *
     * @return array<string, array|string> Validation rules set
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Retrieve all validation errors.
     *
     * @return array List of validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Determine if validation has failed.
     *
     * @return bool True if errors exist
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Alias for hasErrors().
     *
     * @return bool True if validation failed
     */
    public function fails(): bool
    {
        return $this->hasErrors();
    }

    /**
     * Get validated data after successful validation.
     *
     * @param string|null $key Optional field key using dot notation
     * @return mixed Full dataset if null, otherwise specific validated value
     */
    public function validated(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->validatedData;
        }

        return Str::getNestedValue($this->validatedData, $key);
    }
    #endregion Rules

    #region Validation Rules
    /**
     * Validate that a field is present and not empty.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function required(string $field): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            $this->errors[$field] = "The field {$field} is required.";
            return;
        }

        if ($value === '') {
            $this->errors[$field] = "The field {$field} is required.";
        }
    }

    /**
     * Validate that a field contains a valid email address.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function email(string $field): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "The field {$field} must be a valid email address.";
        }
    }

    /**
     * Validate that a field is an array.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function array(string $field): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if (!is_array($value)) {
            $this->errors[$field] = "The field {$field} must be an array.";
        }
    }

    /**
     * Validate that a field has a minimum string length.
     *
     * @param string $field Field name using dot notation
     * @param string $length Minimum allowed length
     * @return void
     */
    protected function min(string $field, string $length): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (strlen((string) $value) < $length) {
            $this->errors[$field] = "The field {$field} must be at least {$length} characters.";
        }
    }

    /**
     * Validate that a field does not exceed maximum length.
     *
     * @param string $field Field name using dot notation
     * @param string $length Maximum allowed length
     * @return void
     */
    protected function max(string $field, string $length): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (strlen((string) $value) > $length) {
            $this->errors[$field] = "The field {$field} may not be greater than {$length} characters.";
        }
    }

    /**
     * Validate that a field has an exact length.
     *
     * @param string $field Field name using dot notation
     * @param string $size Required exact length
     * @return void
     */
    protected function size(string $field, string $size): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (strlen((string) $value) !== (int) $size) {
            $this->errors[$field] = "The field must be {$size} characters.";
        }
    }

    /**
     * Validate that a field is an integer value.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function integer(string $field): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            $this->errors[$field] = "The field {$field} must be an integer.";
        }
    }

    /**
     * Nullable rule placeholder method.
     *
     * This method exists only to allow the rule parser to recognize "nullable".
     * The actual logic is handled during the validation loop.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function nullable(string $field): void
    {
        // Intentionally empty
    }

    /**
     * Validate that a field value is within a predefined set of values.
     *
     * @param string $field Field name using dot notation
     * @param mixed ...$values Allowed values list
     * @return void
     */
    protected function in(string $field, mixed ...$values): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (!in_array($value, $values, true)) {
            $validValues = implode(', ', $values);
            $this->errors[$field] = "The field {$field} must be one of: {$validValues}.";
        }
    }

    /**
     * Validate that a field contains a numeric value.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function numeric(string $field): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (!is_numeric($value)) {
            $this->errors[$field] = "The field {$field} must be numeric.";
        }
    }

    /**
     * Validate that a field is a string value.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function string(string $field): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (!is_string($value)) {
            $this->errors[$field] = "The field {$field} must be a string.";
        }
    }

    /**
     * Validate that a field contains a valid date string.
     *
     * @param string $field Field name using dot notation
     * @return void
     */
    protected function date(string $field): void
    {
        $value = Str::getNestedValue($this->data, $field);

        if ($value === null) {
            return;
        }

        if (!strtotime($value)) {
            $this->errors[$field] = "The field {$field} must be a valid date.";
        }
    }
    #endregion

    #region Data Manipulation
    /**
     * Merge additional values into the validation dataset.
     *
     * @param array $fields Key-value pairs to merge into data
     * @return void
     */
    protected function merge(array $fields): void
    {
        array_walk($fields, function ($value, $key): void {
            Str::setNestedValue($this->data, $key, $value);
        });
    }

    /**
     * Retrieve a value from the input data using dot notation.
     *
     * @param string $field Field name using dot notation
     * @param mixed $default Default value if key does not exist
     * @return mixed
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return Str::getNestedValue($this->data, $field, $default);
    }

    /**
     * Set a value in the input data using dot notation.
     *
     * @param string $field Field name using dot notation
     * @param mixed $value Value to assign
     * @return void
     */
    public function set(string $field, mixed $value): void
    {
        Str::setNestedValue($this->data, $field, $value);
    }

    /**
     * Return the original input data.
     *
     * @return array Raw input data
     */
    public function getAll(): array
    {
        return $this->data;
    }

    /**
     * Determine if a field exists in the dataset using dot notation.
     *
     * @param string $field Field name using dot notation
     * @return bool
     */
    public function has(string $field): bool
    {
        return $this->hasNestedKey($this->data, $field);
    }
    #endregion

    #region Magic Accessor
    /**
     * Magic getter for retrieving input data using dot notation.
     *
     * @param string $name Field name using dot notation
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return Str::getNestedValue($this->data, $name, null);
    }

    /**
     * Magic setter for assigning values using dot notation.
     *
     * @param string $name Field name using dot notation
     * @param mixed $value Value to assign
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        Str::setNestedValue($this->data, $name, $value);
    }

    /**
     * Magic isset check using dot notation.
     *
     * @param string $name Field name using dot notation
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->hasNestedKey($this->data, $name);
    }
    #endregion

    #region Helpers
    /**
     * Determine if a nested key exists inside an array using dot notation.
     *
     * @param array $data Input data array
     * @param string $key Dot notation key path
     * @return bool
     */
    protected function hasNestedKey(array $data, string $key): bool
    {
        if (!str_contains($key, '.')) {
            return array_key_exists($key, $data);
        }

        $keys    = explode('.', $key);
        $current = $data;
        $exists  = true;

        array_walk($keys, function (string $segment) use (&$current, &$exists): void {
            if (!$exists) {
                return;
            }

            if (!is_array($current)) {
                $exists = false;
                return;
            }

            if (!array_key_exists($segment, $current)) {
                $exists = false;
                return;
            }

            $current = $current[$segment];
        });

        return $exists;
    }

    /**
     * Get raw input dataset.
     *
     * @return array
     */
    protected function getData(): array
    {
        return $this->data;
    }

    /**
     * Retrieve a value from input data using dot notation.
     *
     * @param string $field Field name using dot notation
     * @return mixed
     */
    protected function getFieldValue(string $field): mixed
    {
        return Str::getNestedValue($this->data, $field);
    }
    #endregion
}
