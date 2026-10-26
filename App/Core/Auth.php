<?php

namespace App\Core;

use App\Models\Utilizador;
use App\Core\Conexao;

class Auth
{

    private static function startSession(): void
    {
        Sessao::start();
    }

    /**
     * Autentica um utilizador
     */
    public static function attempt(string $email, string $password): false|Utilizador|null
    {
        self::startSession();

        // Normalização completa
        $email = strtolower(trim($email));
        $email = preg_replace('/\s+/u', '', $email);
        $email = str_replace("\u{FEFF}", "", $email);

        $user = Utilizador::findByEmail($email);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user->password)) {
            return false;
        }

        if ($user->ativo == 0 && $user->aprovado_em === null) {
            return null;
        }

        if ($user->ativo == 0 && $user->aprovado_em !== null) {
            return false;
        }

        self::establishSession($user);
        $user->registarLogin();

        return $user;
    }

    public static function register(string $nome, string $email, string $password)
    {
        $email = strtolower(trim($email));
        $email = preg_replace('/\s+/u', '', $email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        $id = Utilizador::create([
            'nome' => $nome,
            'email' => $email,
            'password' => $password,
            'ativo' => 0,
            'aprovado_em' => null,
            'perfil_id' => 2,
        ]);

        if (!$id) {
            return null;
        }

        return Utilizador::find($id);
    }

    /**
     * Login manual
     */
    public static function login(Utilizador $user): void
    {
        self::startSession();
        self::establishSession($user);
    }

    /** Establish an authenticated session and rotate its security tokens. */
    private static function establishSession(Utilizador $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        // Prevent session fixation, then issue a token tied to the new session.
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Não foi possível renovar a sessão.');
        }

        $_SESSION['user_id'] = $user->id;
        CSRF::regenerate();
    }

    /**
     * Carrega o utilizador autenticado (OBJETO COMPLETO)
     */
    public static function user(): ?Utilizador
    {
        self::startSession();

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        // Carrega o utilizador COMPLETO
        $u = Utilizador::find($_SESSION['user_id']);

        if (!$u) {
            return null;
        }

        // Carrega o perfil associado (se existir)
        if (method_exists($u, 'perfil')) {
            $u->perfil = $u->perfil();
        }

        return $u;
    }

    /**
     * ID do utilizador autenticado
     */
    public static function id(): ?int
    {
        $u = self::user();
        return $u?->id;
    }

    /**
     * Verifica se está autenticado
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Registo
     */

    /**
     * Logout seguro
     */
    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            // Mantém os MESMOS parâmetros usados no index.php
            setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
            );
        }

        session_destroy();
    }
}
