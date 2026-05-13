<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Core\Sessao;
use App\Core\Helpers;
use App\Core\Auth;

class LoginController extends BaseController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Página de login
     */
    public function index(): void {
        echo $this->twig->render('login/index.twig');
    }

    /**
     * Autenticação
     */
    public function autenticar(): void {
        $email = $_POST['email'] ?? '';
        $senha = $_POST['password'] ?? '';

        if (empty($email) || empty($senha)) {
            Sessao::flash("danger", "Preencha todos os campos");
            $this->redirect('/login');
        }

        // Usa o sistema de autenticação correto
        $user = Auth::attempt($email, $senha);

        if ($user === false) {
            Sessao::flash("danger", "Credenciais inválidas ou conta bloqueada");
            $this->redirect('/login');
        }

        if ($user === null) {
            Sessao::flash("danger", "A sua conta está pendente de aprovação");
            $this->redirect('/login');
        }

        // Login OK — sessão já foi criada pelo Auth::attempt()
        Helpers::log("Login efetuado", "ID: {$user->id}");
        $this->redirect('/admin/dashboard');
    }

    /**
     * Logout
     */
    public function logout(): void {
        Helpers::log("Logout efetuado");

        Sessao::destruir();
        $this->redirect('/login');
    }
}