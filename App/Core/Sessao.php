<?php

namespace App\Core;

class Sessao
{

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $domain = strtolower(ltrim(trim((string) ($_ENV['SESSION_COOKIE_DOMAIN'] ?? '')), '.'));
            $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $host = preg_replace('/:\\d+$/', '', $host);

            // Um cookie configurado para produção (ex.: anferalta.com) não é
            // aceite pelo browser em localhost/anferalta.local. Nesse caso,
            // usar cookie host-only para manter a sessão nesse ambiente.
            if ($domain === '' || $host === '' || ($host !== $domain && !str_ends_with($host, '.' . $domain))) {
                $domain = null;
            }

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $timeout = max(60, (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800));
        $now = time();
        $lastActivity = (int) ($_SESSION['_last_activity'] ?? 0);
        $authenticated = isset($_SESSION['user_id']) || isset($_SESSION['utilizador_id']);

        if ($lastActivity > 0 && ($now - $lastActivity) >= $timeout) {
            $_SESSION = [];
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Não foi possível renovar a sessão expirada.');
            }
            if ($authenticated) {
                $_SESSION['flash']['erro'] = 'A sessão terminou por inatividade. Inicie sessão novamente.';
            }
        }

        $_SESSION['_last_activity'] = $now;
    }

    // ============================================================
    // CSRF TOKEN
    // ============================================================

    /**
     * Gera ou devolve o token CSRF atual.
     */
    public static function csrfToken(): string
    {
        self::start();
        return CSRF::token();
    }

    /**
     * Valida o token CSRF enviado num POST.
     */
    public static function validarCsrf(string $token): bool
    {
        self::start();
        return CSRF::validateToken($token);
    }

    // ============================================================
    // FLASH MESSAGES
    // ============================================================

    public static function flash($key, $value = null)
    {
        self::start();

        // Gravar
        if (func_num_args() === 2) {
            $_SESSION['flash'][$key] = $value;
            return;
        }

        // Apagar
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    // Ler sem apagar
    public static function peek($key)
    {
        self::start();
        return $_SESSION['flash'][$key] ?? null;
    }

    // Ler tudo sem apagar
    public static function all()
    {
        self::start();
        return $_SESSION['flash'] ?? [];
    }

    // ============================================================
    // SESSÃO NORMAL
    // ============================================================

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        self::start();
        return $_SESSION[$key] ?? null;
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destruir(): void
    {
        self::start();
        session_destroy();
    }

    public static function old(string $key)
    {
        if (!isset($_SESSION['_old'][$key])) {
            return null;
        }

        $value = $_SESSION['_old'][$key];
        unset($_SESSION['_old'][$key]);
        return $value;
    }

    public static function flashOld(string $key, $value): void
    {
        $_SESSION['_old'][$key] = $value;
    }
}
