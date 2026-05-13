<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Core\Auth;
use App\Core\Sessao;
use App\Core\Helpers;
use App\Core\Validator;
use App\Models\Auditoria;
use App\Models\Utilizador;

class AuthController extends BaseController {

    public function login() {
        return $this->render('@site/login/index.twig');
    }

    public function loginSubmit() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = Utilizador::findByEmail($email);

        if (!$user || !password_verify($password, $user->password)) {
            Sessao::flash('erro', 'Credenciais inválidas.');
            Helpers::redirect('/login');
        }

        // Conta pendente
        if ($user->ativo == 0 && $user->aprovado_em === null) {
            Sessao::flash('erro', 'A sua conta está pendente de aprovação.');
            Helpers::redirect('/login');
        }

        // Conta bloqueada
        if ($user->ativo == 0 && $user->aprovado_em !== null) {
            Sessao::flash('erro', 'A sua conta está bloqueada.');
            Helpers::redirect('/login');
        }

        // Login OK
        Auth::login($user);

        // 🔥 REDIRECIONAMENTO POR PERFIL (melhores práticas)
        switch ($user->perfil_id) {

            case 1: // Admin
            case 3: // Gestor
            case 9: // Supervisor
                Helpers::redirect('/admin/dashboard');
                break;

            case 4: // Utilizador
            default:
                Helpers::redirect('/admin/documentos');
                break;
        }
    }

    public function logout() {
        $user = Auth::user();

        if ($user) {
            Auditoria::registar('logout', $user->id);
        }

        Auth::logout();
        return $this->redirect('/login');
    }

    public function registar() {
        return $this->render('@site/login/registar.twig');
    }

    public function registarSubmit() {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['password_confirm'] ?? '');

        $v = new Validator();

        $v->required('nome', $nome, 'Nome obrigatório.');
        $v->required('email', $email, 'Email obrigatório.');
        $v->required('password', $password, 'Password obrigatória.');

        $v->email('email', $email, 'O email não é válido.');

        if (Utilizador::query()->where('email', '=', $email)->first()) {
            $v->addError('email', 'Este email já está registado.');
        }

        $v->min('password', $password, 8, 'A password deve ter pelo menos 8 caracteres.');

        if (!preg_match('/[A-Z]/', $password)) {
            $v->addError('password', 'A password deve conter pelo menos uma letra maiúscula.');
        }

        if (!preg_match('/[a-z]/', $password)) {
            $v->addError('password', 'A password deve conter pelo menos uma letra minúscula.');
        }

        if (!preg_match('/[0-9]/', $password)) {
            $v->addError('password', 'A password deve conter pelo menos um número.');
        }

        if (!preg_match('/[\W_]/', $password)) {
            $v->addError('password', 'A password deve conter pelo menos um símbolo.');
        }

        if ($password !== $confirm) {
            $v->addError('password_confirm', 'As passwords não coincidem.');
        }

        if ($v->hasErrors()) {
            Sessao::flash('erro', implode("<br>", $v->getErrors()));
            return $this->redirect('/registar');
        }

        // Criar utilizador pendente
        $user = Auth::register($nome, $email, $password);

        if (!$user) {
            Sessao::flash('erro', 'Não foi possível criar a conta.');
            return $this->redirect('/registar');
        }

        Sessao::flash('sucesso', 'Conta criada com sucesso! Aguarde aprovação.');
        return $this->redirect('/login');
    }

    public function recuperar() {
        return $this->render('@site/login/recuperar.twig');
    }

    public function recuperarSubmit() {
        $email = trim($_POST['email'] ?? '');

        $v = new Validator();
        $v->required('email', $email, 'Email obrigatório.');

        if ($v->hasErrors()) {
            Sessao::flash('erro', $v->firstError());
            return $this->redirect('/recuperar');
        }

        Auth::sendRecoveryEmail($email);

        Sessao::flash('sucesso', 'Se o email existir, receberá instruções em breve.');
        return $this->redirect('/login');
    }
}
