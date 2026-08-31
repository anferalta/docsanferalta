<?php

namespace App\Core;

class CSRF
{
    private const SESSION_KEY = '_csrf';
    private const FIELD_NAME  = '_csrf';

    /**
     * Gera um novo token e guarda na sessão
     */
    public static function regenerate(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    /**
     * Devolve o token atual ou gera um novo se não existir
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return self::regenerate();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Valida o token vindo do request (POST)
     */
    public static function validateFromRequest(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // GET nunca falha
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }

        $sent   = $_POST[self::FIELD_NAME] ?? null;
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        if (!$sent || !$stored) {
            return false;
        }

        // Comparação segura
        return hash_equals($stored, $sent);
    }

    /**
     * Helper para incluir o campo hidden no Twig
     */
    public static function field(): string
    {
        $token = self::token();

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD_NAME,
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
}
