<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;

class AuthMiddleware
{
    public function handle(): bool
    {
        // Sessão já está iniciada no index.php → não chamar Sessao::start()

        if (!Auth::check()) {
            Redirect::to('/login');
            return false; // parar execução dos middlewares e do controller
        }

        return true; // continuar
    }
}
