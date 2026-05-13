<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Core\Helpers;
use App\Core\BaseController;

class TwoFactorController extends BaseController {

    public function ativar(): void {
        $user = Auth::user();

        if (!$user) {
            Helpers::redirect('/login');
        }

        // Gerar código temporário para ativação
        $codigo = random_int(100000, 999999);
        $expira = date('Y-m-d H:i:s', time() + 300);

        $user->updateUser($user->id, [
            'two_factor_code' => $codigo,
            'two_factor_expires' => $expira
        ]);

        // Enviar código por email
        Mail::to($user->email)->send(
                "O seu código de ativação 2FA é: {$codigo}"
        );

        Sessao::flash('info', 'Enviámos um código para ativar o 2FA');
        $this->view('auth/2fa_ativar', [
            'csrf' => Sessao::csrf()
        ]);
    }

    public function confirmar(): void {
        Sessao::start();

        $token = $_POST['_csrf'] ?? '';
        if (!Sessao::validarCsrf($token)) {
            Sessao::flash('error', 'Token CSRF inválido');
            Helpers::redirect('/2fa/ativar');
        }

        $codigo = trim($_POST['codigo'] ?? '');
        $user = Auth::user();

        if (!$user) {
            Helpers::redirect('/login');
        }

        // Validar código
        if ($user->two_factor_code !== $codigo ||
                strtotime($user->two_factor_expires) < time()) {

            Sessao::flash('error', 'Código inválido ou expirado');
            Helpers::redirect('/2fa/ativar');
        }

        // Ativar 2FA
        $user->updateUser($user->id, [
            'two_factor_ativo' => 1,
            'two_factor_code' => null,
            'two_factor_expires' => null
        ]);

        Sessao::flash('success', '2FA ativado com sucesso');
        Helpers::redirect('/admin/seguranca');
        Helpers::redirect('/admin');
    }
}
