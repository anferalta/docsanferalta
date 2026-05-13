<?php

namespace App\Core;

use App\Models\Utilizador;
use App\Core\Conexao;

class Auth {

    private static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Autentica um utilizador
     */
    public static function attempt(string $email, string $password): false|Utilizador|null {
        self::startSession();

        $user = Utilizador::findByEmail($email);

        if (!$user) {
            return false;
        }

        // Senha incorreta
        if (!password_verify($password, $user->password)) {
            return false;
        }

        // Conta pendente
        if ($user->ativo == 0 && $user->aprovado_em === null) {
            return null;
        }

        // Conta bloqueada
        if ($user->ativo == 0 && $user->aprovado_em !== null) {
            return false;
        }

        // Conta ativa
        if ($user->ativo == 1 && $user->aprovado_em !== null) {

            // Guardar ID na sessão
            $_SESSION['user_id'] = $user->id;

            // Registar login
            $user->registarLogin();

            return $user;
        }

        return false;
    }

    /**
     * Login manual
     */
    public static function login(Utilizador $user): void {
        self::startSession();

        // ❌ REMOVIDO: session_regenerate_id(true)
        // Isto estava a invalidar o cookie antes do redirect

        // Se quiseres regenerar, usa:
        // session_regenerate_id(false);

        $_SESSION['user_id'] = $user->id;
    }

    /**
     * Carrega o utilizador autenticado (OBJETO COMPLETO)
     */
    public static function user(): ?Utilizador {
        self::startSession();

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $db = Conexao::getInstancia();
        $stmt = $db->prepare("SELECT * FROM utilizadores WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['user_id']]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        $u = new Utilizador();

        foreach ($data as $key => $value) {
            if (property_exists($u, $key)) {
                $u->$key = $value;
            }
        }

        return $u;
    }

    /**
     * ID do utilizador autenticado
     */
    public static function id(): ?int {
        $u = self::user();
        return $u?->id;
    }

    /**
     * Verifica se está autenticado
     */
    public static function check(): bool {
        return self::user() !== null;
    }

    /**
     * Registo
     */
    public static function register(string $nome, string $email, string $password) {

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
     * Logout seguro
     */
    public static function logout(): void {
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
