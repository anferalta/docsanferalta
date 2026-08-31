<?php

namespace App\Models;

use App\Core\Conexao;
use App\Models\Utilizador;

class PasswordReset
{
    /**
     * Criar token de recuperação
     */
    public static function criarToken(string $email): string
    {
        // Normalizar email antes de gravar
        $email = Utilizador::normalizeEmail($email);

        $token = bin2hex(random_bytes(32));
        $db = Conexao::getInstancia();

        // Apagar tokens antigos deste email
        $db->prepare("DELETE FROM password_resets WHERE email = ?")
           ->execute([$email]);

        // Inserir novo token
        $stmt = $db->prepare("
            INSERT INTO password_resets (email, token, criado_em)
            VALUES (:email, :token, NOW())
        ");

        $stmt->execute([
            ':email' => $email,
            ':token' => $token
        ]);

        return $token;
    }

    /**
     * Validar token e devolver email associado
     */
    public static function validarToken(string $token): ?string
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
            SELECT email, criado_em
            FROM password_resets
            WHERE token = ?
        ");

        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Expira em 60 minutos
        $expira = strtotime($row['criado_em']) + 3600;

        if (time() > $expira) {
            self::apagarToken($token);
            return null;
        }

        // Normalizar email antes de devolver
        return Utilizador::normalizeEmail($row['email']);
    }

    /**
     * Apagar token
     */
    public static function apagarToken(string $token): void
    {
        $db = Conexao::getInstancia();
        $db->prepare("DELETE FROM password_resets WHERE token = ?")
           ->execute([$token]);
    }
}
