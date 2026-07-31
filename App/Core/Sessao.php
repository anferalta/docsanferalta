<?php

namespace App\Core;

class Sessao
{
    private static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
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

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Valida o token CSRF enviado num POST.
     */
    public static function validarCsrf(string $token): bool
    {
        self::start();

        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
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
}
