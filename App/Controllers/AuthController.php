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
            $password = trim($_POST['password'] ?? '');

            $user = Utilizador::findByEmail($email);

            if (!$user || !password_verify($password, $user->password)) {
                Sessao::flash('erro', 'Credenciais inválidas.');
                return Helpers::redirect('/login');
            }

            // ANTI‑FIXATION — ADICIONAR AQUI
            session_regenerate_id(true);

            Auth::login($user);
            Auditoria::registar('login', $user->id);

            if ($user->ativo == 0 && $user->aprovado_em === null) {
                Sessao::flash('erro', 'A sua conta está pendente de aprovação.');
                return Helpers::redirect('/login');
            }

            if ($user->ativo == 0 && $user->aprovado_em !== null) {
                Sessao::flash('erro', 'A sua conta está bloqueada.');
                return Helpers::redirect('/login');
            }

            Auth::login($user);
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

// DESTRUIÇÃO TOTAL DA SESSÃO
            session_unset();
            session_destroy();

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                        session_name(),
                        '',
                        time() - 3600,
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly']
                );
            }

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

            EmailService::enviar(
                    $email,
                    'Conta criada — aguarda aprovação',
                    'utilizador_criado.twig',
                    [
                        'nome' => $nome,
                        'link' => 'A sua conta aguarda aprovação do administrador.'
                    ]
            );

            Sessao::flash('sucesso', 'Conta criada com sucesso! Aguarde aprovação.');
            return Helpers::redirect('/login');
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }
}
