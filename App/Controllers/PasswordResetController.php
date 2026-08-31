<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Core\Sessao;
use App\Core\Helpers;
use App\Services\EmailService;
use App\Core\Auth;
use App\Core\CSRF;

class PasswordResetController extends BaseController
{

    /**
     * GET /recuperar
     */
    public function solicitar()
    {
        $email = $_SESSION['email_recuperar'] ?? '';
        unset($_SESSION['email_recuperar']);

        $this->view('@site/password/recuperar.twig', [
            'email' => $email
        ]);
    }

    /**
     * POST /recuperar
     */
    public function enviarLink()
    {
        $email = trim($_POST['email'] ?? '');

        $_SESSION['email_recuperar'] = $email;

        if ($email === '') {
            $this->flash('erro', 'Indique o email associado à conta.');
            return $this->redirect('/recuperar');
        }

        $db = Conexao::getInstancia();

        // Verificar se existe utilizador
        $stmt = $db->prepare("SELECT nome FROM utilizadores WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Mensagem neutra
        if (!$user) {
            $this->flash('sucesso', 'Se o email existir, receberá um link para recuperar a password.');
            return $this->redirect('/recuperar');
        }

        // Token seguro
        $token = bin2hex(random_bytes(32));

        // UPSERT
        $stmt = $db->prepare("
            INSERT INTO password_resets (email, token, criado_em)
            VALUES (:email, :token, NOW())
            ON DUPLICATE KEY UPDATE
                token     = VALUES(token),
                criado_em = NOW()
        ");
        $stmt->execute([
            ':email' => $email,
            ':token' => $token
        ]);

        // Enviar email real
        EmailService::enviar(
                $email,
                'Recuperar senha',
                'password_reset',
                [
                    'nome' => $user->nome ?? $email,
                    'link' => "https://anferaltadocs.local/reset-password/token/{$token}"
                ]
        );

        $this->flash('sucesso', 'Se o email existir, receberá um link para recuperar a password.');
        return $this->redirect('/recuperar');
    }

    /**
     * GET /reset-password/token/{token}
     */
    public function formNovaPassword(string $token)
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
            SELECT email, criado_em
            FROM password_resets
            WHERE token = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $this->flash('erro', 'Link inválido ou já utilizado.');
            return $this->redirect('/recuperar');
        }

        // Expiração (60 minutos)
        $criado = new \DateTime($reset->criado_em);
        $agora = new \DateTime();
        $diff = $criado->diff($agora);

        $minutos = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;

        if ($minutos > 60) {
            $this->flash('erro', 'Este link expirou. Peça um novo.');
            return $this->redirect('/recuperar');
        }

        // Mostrar formulário
        return $this->view('@site/password/nova_password.twig', [
                    'token' => $token,
                    'email' => $reset->email
        ]);
    }

    /**
     * POST /reset-password/guardar/{token}
     */
    public function guardarNovaPassword(string $token)
    {
        // ============================
        // CSRF
        // ============================
        if (!CSRF::validateFromRequest()) {
            $this->flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/recuperar');
        }

        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // ============================
        // Validação da password
        // ============================
        if ($password === '' || $password_confirm === '') {
            $this->flash('erro', 'Preencha ambos os campos.');
            return $this->redirect("/reset-password/token/{$token}");
        }

        if ($password !== $password_confirm) {
            $this->flash('erro', 'As passwords não coincidem.');
            return $this->redirect("/reset-password/token/{$token}");
        }

        if (strlen($password) < 6) {
            $this->flash('erro', 'A password deve ter pelo menos 6 caracteres.');
            return $this->redirect("/reset-password/token/{$token}");
        }

        $db = Conexao::getInstancia();

        // ============================
        // Validar token
        // ============================
        $stmt = $db->prepare("
            SELECT email, criado_em
            FROM password_resets
            WHERE token = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $this->flash('erro', 'Link inválido ou já utilizado.');
            return $this->redirect('/recuperar');
        }

        // ============================
        // Expiração (60 minutos)
        // ============================
        $criado = new \DateTime($reset->criado_em);
        $agora = new \DateTime();
        $diff = $criado->diff($agora);

        $minutos = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;

        if ($minutos > 60) {
            $this->flash('erro', 'Este link expirou.');
            return $this->redirect('/recuperar');
        }

        // ============================
        // Atualizar password
        // ============================
        $email = $reset->email;
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            UPDATE utilizadores
            SET password = :password
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([
            ':password' => $hash,
            ':email' => $email
        ]);

        // ============================
        // Invalidar token
        // ============================
        $stmt = $db->prepare("DELETE FROM password_resets WHERE email = :email");
        $stmt->execute([':email' => $email]);

        // ============================
        // Logout (agora sim!)
        // ============================
        Auth::logout();

        // ============================
        // Mensagem + redirecionamento
        // ============================
        $this->flash('sucesso', 'Password alterada com sucesso. Faça login.');
        return $this->redirect('/login');
    }
}
