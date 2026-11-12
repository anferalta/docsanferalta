<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Core\Sessao;
use App\Core\Helpers;
use App\Core\Validator;
use App\Core\CSRF;
use App\Models\Auditoria;
use App\Models\Utilizador;
use App\Services\EmailService;

class AuthController extends BaseController
{

    public function login()
    {
        return $this->render('site/login/index.twig', [
            '_csrf' => CSRF::token(),
            'erro' => Sessao::peek('erro'),
            'sucesso' => Sessao::peek('sucesso'),
        ]);
    }

    public function loginSubmit()
    {
        try {
            $email = Utilizador::normalizeEmail($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            $user = Auth::attempt($email, $password);
            if ($user === false) {
                Sessao::flash('erro', 'Credenciais inválidas.');
                return Helpers::redirect('/login');
            }

            if ($user === null) {
                Sessao::flash('erro', 'A sua conta está pendente de aprovação.');
                return Helpers::redirect('/login');
            }

            Auditoria::registar('login', $user->id);

            if ($user->hasPermissao('admin.dashboard.ver')) {
                return Helpers::redirect('/admin/dashboard');
            }

            return Helpers::redirect('/dashboard');
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    public function logout()
    {
        try {
            $user = Auth::user();

            if ($user) {
                Auditoria::registar('logout', $user->id);
            }

            Auth::logout();
            return Helpers::redirect('/login');
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    public function registar()
    {
        return $this->render('site/login/registar.twig', [
            '_csrf' => CSRF::token()
        ]);
    }

    public function registarSubmit()
    {
        try {
            $nome = trim($_POST['nome'] ?? '');
            $email = Utilizador::normalizeEmail($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirm = trim($_POST['password_confirm'] ?? '');

            $v = new Validator();

            $v->required('nome', $nome, 'Nome obrigatório.');
            $v->required('email', $email, 'Email obrigatório.');
            $v->required('password', $password, 'Password obrigatória.');
            $v->email('email', $email, 'O email não é válido.');

            if (Utilizador::findByEmail($email)) {
                $v->addError('email', 'Este email já está registado.');
            }

            if ($password !== $confirm) {
                $v->addError('password_confirm', 'As passwords não coincidem.');
            }

            if ($v->hasErrors()) {
                Sessao::flash('erro', implode("<br>", $v->getErrors()));
                Sessao::flash('old_nome', $nome);
                Sessao::flash('old_email', $email);
                return Helpers::redirect('/registar');
            }

            $user = Auth::register($nome, $email, $password);

            if (!$user) {
                return $this->error(500, "Não foi possível criar a conta.");
            }

            // A conta já está persistida. A notificação não pode transformar
            // um registo concluído num erro nem ativar a conta sem aprovação.
            $notificado = false;
            try {
                $notificado = EmailService::enviar(
                    $email,
                    'Conta criada — aguarda aprovação',
                    'utilizador_criado.twig',
                    [
                        'nome' => $nome,
                        'link' => 'A sua conta aguarda aprovação do administrador.'
                    ]
                );
            } catch (\Throwable $e) {
                // O EmailService regista o diagnóstico SMTP em privado.
                error_log('Falha na notificação de registo; utilizador_id=' . $user->id . '; tipo=' . get_class($e));
            }

            Sessao::flash('sucesso', 'Conta criada com sucesso! Aguarde aprovação.');
            if (!$notificado) {
                Sessao::flash('aviso', 'Não foi possível enviar o email de confirmação. Confirme o endereço de email com o administrador. A sua conta continua pendente de aprovação.');
            }
            return Helpers::redirect('/login');
        } catch (\Exception $e) {
            error_log('Falha no registo de utilizador: ' . $e->getMessage());
            return $this->error(500, 'Não foi possível concluir o registo. Tente novamente mais tarde.');
        }
    }
}
