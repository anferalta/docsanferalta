<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Helpers;
use App\Core\Sessao;
use App\Core\Email;

class RecuperacaoController extends BaseController
{
    public function form(): void
    {
        $this->view('auth/recuperar', [
            'csrf' => Sessao::csrf()
        ]);
    }

    public function enviar(): void
    {
        Sessao::start();

        $email = trim($_POST['email'] ?? '');
        $token = Auth::gerarTokenRecuperacao($email);

        if (!$token) {
            Sessao::flash('error', 'Email não encontrado');
            Helpers::redirect('/recuperar');
        }

        $link = Helpers::url("/reset/$token");

        Email::enviar(
            $email,
            "Recuperação de Conta",
            "Clique no link para redefinir a sua password:<br><a href='$link'>$link</a>"
        );

        Sessao::flash('success', 'Enviámos um email com instruções de recuperação');
        Helpers::redirect('/recuperar');
    }

    public function resetForm(string $token): void
    {
        $user = Auth::validarToken($token);

        if (!$user) {
            Sessao::flash('error', 'Token inválido ou expirado');
            Helpers::redirect('/recuperar');
        }

        $this->view('auth/reset', [
            'csrf' => Sessao::csrf(),
            'token' => $token
        ]);
    }

    public function reset(string $token): void
    {
        Sessao::start();

        $user = Auth::validarToken($token);

        if (!$user) {
            Sessao::flash('error', 'Token inválido ou expirado');
            Helpers::redirect('/recuperar');
        }

        $password = $_POST['password'] ?? '';
        Auth::redefinirPassword($user->id, $password);

        Sessao::flash('success', 'Password redefinida com sucesso');
        Helpers::redirect('/login');
    }
}