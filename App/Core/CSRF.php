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
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return true;
        }

        // Older views and the csrf_field() helper submit `csrf`, while newer
        // views use `_csrf`. Accept both names against the same session token.
        $sent   = $_POST[self::FIELD_NAME]
            ?? $_POST['csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($sent) || !is_string($stored) || $sent === '' || $stored === '') {
            return false;
        }

        // Comparação segura
        return self::validateToken($sent);
    }

    /** Valida um token fornecido diretamente pelo código da aplicação. */
    public static function validateToken(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        return $token !== '' && is_string($stored) && hash_equals($stored, $token);
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

    /** Interrompe pedidos inválidos sem executar a ação nem expor uma stack trace. */
    public static function exigirTokenValido(): void
    {
        if (self::validateFromRequest()) {
            return;
        }
        http_response_code(419);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, private');
        echo '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Formulário expirado</title></head><body>';
        echo '<main style="max-width:40rem;margin:3rem auto;padding:1rem;font-family:system-ui,sans-serif"><h1>O formulário expirou</h1>';
        echo '<p>A sessão mudou ou o formulário já não é válido. Volte a abrir a página e tente novamente.</p><p>A operação não foi executada.</p>';
        echo '<p><a href="/login">Iniciar sessão</a> · <a href="/admin">Voltar ao painel</a></p></main></body></html>';
        exit;
    }
}
