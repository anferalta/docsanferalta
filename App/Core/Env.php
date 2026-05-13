<?php
namespace App\Core;

class Env
{
    public static function load(): void
    {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (strpos($line, '=') !== false) {
                    [$name, $value] = explode('=', $line, 2);
                    $_ENV[trim($name)] = trim($value);
                }
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}