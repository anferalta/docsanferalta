<?php

namespace App\Core;

class CSRF
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME  = '_csrf';

    /**
     * Garante que a sessão está ativa
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Gera ou devolve o token CSRF
     */
    public static function token(): string
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Nome do campo CSRF para formulários
     */
    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    /**
     * Valida o token vindo do POST ou do header X-CSRF-Token
     */
    public static function validateFromRequest(): bool
    {
        self::ensureSession();

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;

        $requestToken =
            $_POST[self::FIELD_NAME] ??
            $_SERVER['HTTP_X_CSRF_TOKEN'] ??
            null;

        if (!$sessionToken || !$requestToken) {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }

    /**
     * Regenera o token CSRF (ex: após login)
     */
    public static function regenerate(): void
    {
        self::ensureSession();

        unset($_SESSION[self::SESSION_KEY]);
        self::token();
    }

    /**
     * Middleware CSRF para proteger rotas POST
     */
    public static function middleware(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!self::validateFromRequest()) {

                // Registar tentativa falhada na auditoria
                \App\Models\Auditoria::registar(
                    'CSRF_FALHOU',
                    \App\Core\Auth::id(),
                    'Token CSRF inválido ou ausente'
                );

                header('HTTP/1.1 419 CSRF Token Invalid');
                echo "Token CSRF inválido.";
                exit;
            }
        }
    }
}