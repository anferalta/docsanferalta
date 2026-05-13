<?php

namespace App\Middleware;

use App\Models\Auditoria;
use App\Core\Auth;

class LogMiddleware {

    public static function registar(string $acao): void {
        Auditoria::create([
            'utilizador_id' => Auth::user()['id'] ?? null,
            'acao' => $acao,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
