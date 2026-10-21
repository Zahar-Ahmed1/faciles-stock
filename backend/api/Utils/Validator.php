<?php

declare(strict_types=1);

namespace App\Utils;

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self
    {
        $validator = new self($data);

        foreach ($rules as $field => $fieldRules) {
            $fieldRuleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            foreach ($fieldRuleList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramString] = explode(':', $rule, 2);
                    $params = explode(',', $paramString);
                }
                $validator->validateRule($field, $rule, $params);
            }
        }

        return $validator;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new \RuntimeException('Validation failed, cannot get validated data');
        }

        $validated = [];
        foreach ($this->data as $key => $value) {
            $validated[$key] = $value;
        }

        return $validated;
    }

    private function validateRule(string $field, string $rule, array $params): void
    {
        $value = $this->data[$field] ?? null;
        $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
    }

    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'required', "The {$field} field is required");
        }
    }

    private function validateNullable(string $field, mixed $value): void
    {
    }

    private function validateString(string $field, mixed $value): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, 'string', "The {$field} field must be a string");
        }
    }

    private function validateInt(string $field, mixed $value): void
    {
        if ($value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, 'int', "The {$field} field must be an integer");
        }
    }

    private function validateInteger(string $field, mixed $value): void
    {
        $this->validateInt($field, $value);
    }

    private function validateFloat(string $field, mixed $value): void
    {
        if ($value !== null && filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
            $this->addError($field, 'float', "The {$field} field must be a float");
        }
    }

    private function validateNumeric(string $field, mixed $value): void
    {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($field, 'numeric', "The {$field} field must be numeric");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', "The {$field} field must be a valid email");
        }
    }

    private function validateMin(string $field, mixed $value, array $params): void
    {
        $min = (int)($params[0] ?? 0);

        if (is_string($value)) {
            if (mb_strlen($value) < $min) {
                $this->addError($field, 'min', "The {$field} must be at least {$min} characters");
            }
        } elseif (is_numeric($value)) {
            if ($value < $min) {
                $this->addError($field, 'min', "The {$field} must be at least {$min}");
            }
        }
    }

    private function validateMax(string $field, mixed $value, array $params): void
    {
        $max = (int)($params[0] ?? 0);

        if (is_string($value)) {
            if (mb_strlen($value) > $max) {
                $this->addError($field, 'max', "The {$field} must not exceed {$max} characters");
            }
        } elseif (is_numeric($value)) {
            if ($value > $max) {
                $this->addError($field, 'max', "The {$field} must not exceed {$max}");
            }
        }
    }

    private function validateIn(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !in_array($value, $params, true)) {
            $this->addError($field, 'in', "The selected {$field} is invalid");
        }
    }

    private function validateBoolean(string $field, mixed $value): void
    {
        if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
            $this->addError($field, 'boolean', "The {$field} field must be true or false");
        }
    }

    private function validateUrl(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url', "The {$field} field must be a valid URL");
        }
    }

    private function validateUuid(string $field, mixed $value): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if ($value !== null && !preg_match($pattern, (string)$value)) {
            $this->addError($field, 'uuid', "The {$field} field must be a valid UUID");
        }
    }

    private function validateDate(string $field, mixed $value): void
    {
        if ($value !== null) {
            $timestamp = strtotime((string)$value);
            if ($timestamp === false) {
                $this->addError($field, 'date', "The {$field} field must be a valid date");
            }
        }
    }

    private function validateArray(string $field, mixed $value): void
    {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, 'array', "The {$field} field must be an array");
        }
    }

    private function validateConfirmed(string $field, mixed $value): void
    {
        $confirmedField = $field . '_confirmation';
        $confirmedValue = $this->data[$confirmedField] ?? null;

        if ($value !== null && $value !== $confirmedValue) {
            $this->addError($field, 'confirmed', "The {$field} confirmation does not match");
        }
    }

    private function addError(string $field, string $rule, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][$rule] = $message;
    }
}
