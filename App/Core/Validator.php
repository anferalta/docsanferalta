<?php
namespace App\Core;

class Validator
{
    private array $errors = [];

    public function required(string $field, $value, string $message): void
    {
        if (trim($value) === '') {
            $this->errors[$field] = $message;
        }
    }

    public function email(string $field, $value, string $message): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message;
        }
    }

    public function min(string $field, $value, int $min, string $message): void
    {
        if (strlen($value) < $min) {
            $this->errors[$field] = $message;
        }
    }

    // ⭐ ADICIONAR ESTE MÉTODO ⭐
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}