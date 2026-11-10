<?php
namespace App\Core;
class Env
{
    public static function load(): void
    {
        foreach (getenv() as $name => $value) {
            $_ENV[$name] = $value;
        }
        \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
    }
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        return $value !== false ? $value : ($_ENV[$key] ?? $default);
    }
    public static function required(string $key): string
    {
        $value = self::get($key);
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException('Configuração obrigatória em falta: ' . $key);
        }
        return $value;
    }

    public static function readSecretFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) > 16384) {
            throw new \RuntimeException('Ficheiro privado indisponível.');
        }
        $value = file_get_contents($path);
        if ($value === false) throw new \RuntimeException('Não foi possível ler o ficheiro privado.');
        return rtrim($value, "\r\n");
    }

    public static function secret(string $key): string
    {
        $file = self::get($key . '_FILE', '');
        return $file !== '' ? self::readSecretFile((string) $file) : (string) self::get($key, '');
    }
}
