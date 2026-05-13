<?php

namespace App\Services;

use App\Models\Utilizador;
use App\Core\Sessao;

class Auth {

    public function user(): ?Utilizador {
        $id = Sessao::get('user_id');

        if (!$id) {
            return null;
        }

        return (new Utilizador())->find($id);
    }

    public function check(): bool {
        return Sessao::has('user_id');
    }

    public function logout() {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }
}
