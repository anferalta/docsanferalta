<?php

namespace App\Core;

class Sessao {

    /**
     * Garante que a sessão está iniciada
     */
    private static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Define ou obtém uma flash message
     */
    public static function flash($key, $value = null) {
        self::start(); // ⭐ OBRIGATÓRIO

        if ($value !== null) {
            $_SESSION['flash'][$key] = $value;
            return;
        }

        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }

        return null;
    }

    /**
     * Obtém e limpa todas as flash messages
     */
    public static function getFlash(): array {
        self::start(); // ⭐ OBRIGATÓRIO

        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return $flash;
    }

    /**
     * Define um valor de sessão normal
     */
    public static function set(string $key, mixed $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Obtém um valor de sessão
     */
    public static function get(string $key): mixed {
        self::start();
        return $_SESSION[$key] ?? null;
    }

    /**
     * Remove um valor específico
     */
    public static function remove(string $key): void {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destrói toda a sessão
     */
    public static function destruir(): void {
        self::start();
        session_destroy();
    }
}