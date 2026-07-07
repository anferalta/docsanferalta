<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Sessao;
use App\Core\Helpers;
use App\Models\PasswordReset;
use App\Models\Utilizador;
use App\Services\EmailService;

class PasswordResetController extends BaseController
{

    public function solicitar()
    {
        return $this->render('site/login/recuperar.twig');
    }

    public function enviarLink()
    {
        $email = trim($_POST['email'] ?? '');

        $user = Utilizador::findByEmail($email);

        if ($user) {
            $token = PasswordReset::criarToken($email);

            EmailService::enviar(
                    $email,
                    'Recuperação de Password',
                    'reset_password',
                    [
                        'nome' => $user->nome,
                        'link' => Helpers::baseUrl() . "/reset-password/token/$token"
                    ]
            );
        }

        Sessao::flash('sucesso', 'Se o email existir, receberá instruções em breve.');
        return Helpers::redirect('/login');
    }

    public function formNovaPassword($token)
    {
        $email = PasswordReset::validarToken($token);

        if (!$email) {
            Sessao::flash('erro', 'Link inválido ou expirado.');
            return Helpers::redirect('/recuperar');
        }

        return $this->render('site/login/nova_password.twig', [
                    'token' => $token,
                    'csrf_token' => Sessao::csrfToken()
        ]);
    }

    public function guardarNovaPassword()
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        $email = PasswordReset::validarToken($token);

        if (!$email) {
            Sessao::flash('erro', 'Link inválido ou expirado.');
            return Helpers::redirect('/recuperar');
        }

        if ($password !== $confirm) {
            Sessao::flash('erro', 'As passwords não coincidem.');
            return Helpers::redirect("/reset-password/token/$token");
        }

        if (strlen($password) < 8 ||
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[0-9]/', $password) ||
                !preg_match('/[\W_]/', $password)) {

            Sessao::flash('erro', 'A password deve ter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas, números e símbolos.');
            return Helpers::redirect("/reset-password/token/$token");
        }

        $user = Utilizador::findByEmail($email);

        $user->update([
            'password' => password_hash($password, PASSWORD_DEFAULT)
                ], "email = :email", [':email' => $email]);

        PasswordReset::apagarToken($token);

        Sessao::flash('sucesso', 'Password alterada com sucesso.');
        return Helpers::redirect('/login');
    }

    public function redirecionarSemToken()
    {
        Sessao::flash('erro', 'Link inválido ou expirado.');
        return Helpers::redirect('/recuperar');
    }
    
}
