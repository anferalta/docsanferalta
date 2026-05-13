<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Sessao;

class TwoFactorMiddleware
{
    public function handle(): bool
    {
        Sessao::start(); // garantir sessão ativa

        // Se não está autenticado, não valida 2FA
        if (!Auth::check()) {
            return true;
        }

        $user = Auth::user();

        // Se o utilizador não tem 2FA ativo, deixa passar
        if (empty($user->two_factor_ativo)) {
            return true;
        }

        // Se já validou 2FA nesta sessão, deixa passar
        if (!empty($_SESSION['2fa_validated']) && $_SESSION['2fa_validated'] === true) {
            return true;
        }

        // Rotas permitidas sem validação 2FA
        $allowed = [
            '/2fa/ativar',
            '/2fa/confirmar',
            '/2fa/validar',
            '/2fa/enviar',
            '/logout'
        ];

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        foreach ($allowed as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }

        // Se chegou aqui, tem 2FA ativo mas não validado
        Redirect::to('/2fa/validar');
        return false; // parar execução
    }
}