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
        $csrf = CSRF::token();

        // LER SEM APAGAR → porque o layout já consome o flash
        $erro = Sessao::peek('erro');
        $sucesso = Sessao::peek('sucesso');

        return $this->render('site/login/index.twig', [
            'csrf'    => $csrf,
            'erro'    => $erro,
            'sucesso' => $sucesso,
        ]);
    }

    public function loginSubmit()
    {
        try {

            $email = strtolower(trim($_POST['email'] ?? ''));
            $email = preg_replace('/\s+/u', '', $email);

            $password = trim($_POST['password'] ?? '');

            $user = Utilizador::findByEmail($email);

            if (!$user || !password_verify($password, $user->password)) {
                Sessao::flash('erro', 'Credenciais inválidas.');
                return Helpers::redirect('/login');
            }

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

            return $user->isAdmin()
                ? Helpers::redirect('/admin/dashboard')
                : Helpers::redirect('/dashboard');

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
        return $this->render('site/login/registar.twig');
    }

    public function registarSubmit()
    {
        try {

            $nome = trim($_POST['nome'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $email = preg_replace('/\s+/u', '', $email);
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

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
                'Conta criado — aguarda aprovação',
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

    public function recuperar()
    {
        return $this->render('site/login/recuperar.twig');
    }

    public function recuperarSubmit()
    {
        try {

            $email = strtolower(trim($_POST['email'] ?? ''));
            $email = preg_replace('/\s+/u', '', $email);

            $v = new Validator();
            $v->required('email', $email, 'Email obrigatório.');

            if ($v->hasErrors()) {
                Sessao::flash('erro', $v->firstError());
                return Helpers::redirect('/recuperar');
            }

            (new \App\Controllers\PasswordResetController())->enviarLink($email);

            Sessao::flash('sucesso', 'Se o email existir, receberá instruções em breve.');
            return Helpers::redirect('/login');

        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }
}
