<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Sessao;
use App\Core\Redirect;

class AuthMiddleware
{
    public function handle(): bool
    {
        Sessao::start(); // obrigatório

        if (!Auth::check()) {
            Redirect::to('/login');
            return false; // parar execução dos middlewares e do controller
        }

        return true; // continuar
    }
}