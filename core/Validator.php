<?php

final class Validator
{
    private array $errors = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {}

    public static function make(array $data, array $rules): self
    {
        $v = new self($data, $rules);
        $v->run();
        return $v;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                [$ruleName, $param] = [...explode(':', $rule, 2), null];

                $error = match ($ruleName) {
                    'required' => ($value === null || $value === '')
                        ? "{$field} is required" : null,
                    'string' => (!is_null($value) && !is_string($value))
                        ? "{$field} must be a string" : null,
                    'email' => ($value && !filter_var($value, FILTER_VALIDATE_EMAIL))
                        ? "{$field} must be a valid email" : null,
                    'min' => (is_string($value) && strlen($value) < (int) $param)
                        ? "{$field} must be at least {$param} characters" : null,
                    'max' => (is_string($value) && strlen($value) > (int) $param)
                        ? "{$field} must be at most {$param} characters" : null,
                    'numeric' => ($value && !is_numeric($value))
                        ? "{$field} must be numeric" : null,
                    'integer' => ($value && !ctype_digit((string) $value))
                        ? "{$field} must be an integer" : null,
                    'in' => ($value && !in_array($value, explode(',', $param ?? '')))
                        ? "{$field} must be one of: {$param}" : null,
                    default => null,
                };

                if ($error) {
                    $this->errors[$field][] = $error;
                    break;
                }
            }
        }
    }
}
