<?php
namespace App\Services;

use App\Core\Env;
use PDO;

final class DatabaseConnection
{
    public static function open(?array $config = null): PDO
    {
        $get = static fn(string $key, mixed $default = null) => $config === null ? Env::get($key, $default) : ($config[$key] ?? $default);
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $key) {
            if (!is_string($get($key)) || $get($key) === '') throw new \RuntimeException('Configuração em falta: ' . $key);
        }
        $port = filter_var($get('DB_PORT', 3306), FILTER_VALIDATE_INT);
        if (!$port || $port < 1 || $port > 65535) throw new \RuntimeException('DB_PORT inválido.');
        $password = $config === null ? Env::secret('DB_PASS') : (string) $get('DB_PASS', '');
        if ($config !== null && !empty($config['DB_PASS_FILE'])) $password = Env::readSecretFile($config['DB_PASS_FILE']);
        foreach (['DB_HOST', 'DB_NAME'] as $key) {
            if (preg_match('/[;\x00-\x1f]/', $get($key))) throw new \RuntimeException('Configuração de ligação inválida.');
        }
        return new PDO('mysql:host=' . $get('DB_HOST') . ';port=' . $port . ';dbname=' . $get('DB_NAME') . ';charset=utf8mb4', $get('DB_USER'), $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function identifier(string $name): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/D', $name)) throw new \RuntimeException('Identificador inválido.');
        return '`' . $name . '`';
    }
}
