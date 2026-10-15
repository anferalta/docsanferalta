<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;

class AuthMiddleware
{
    public function handle(): bool
    {
        // Sessão já está iniciada no index.php
        // NÃO chamar Sessao::start()

        if (!Auth::check()) {
            Redirect::to('/login');
            return false; // parar execução
        }

        return true; // continuar
    }
}
