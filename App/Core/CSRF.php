<?php

namespace App\Core;

class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    // Garantir que o token existe ANTES do controller
    public static function ensureTokenExists(): void
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
    }

    // Obter token atual (sem regenerar)
    public static function token(): string
    {
        self::ensureTokenExists();
        return $_SESSION[self::SESSION_KEY];
    }

    // Validar token enviado pelo cliente
    public static function validateFromRequest(): bool
    {
        self::ensureSession();

        $clientToken =
            $_POST['_csrf'] ??
            $_SERVER['HTTP_X_CSRF_TOKEN'] ??
            null;

        if (!$clientToken) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $clientToken);
    }

    // Rotacionar token após POST válido
    public static function rotate(): void
    {
        self::ensureSession();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }
}
