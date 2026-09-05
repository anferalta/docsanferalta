<?php

namespace App\Middleware;

use App\Core\CSRF;

class CsrfMiddleware
{
    public function handle(): void
    {
        // Garantir que o token existe ANTES do controller
        CSRF::ensureTokenExists();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {

            // Validar token enviado pelo cliente
            if (!CSRF::validateFromRequest()) {
                http_response_code(419);
                die('Sessão expirada ou token CSRF inválido.');
            }

            // Rotacionar token após POST válido
            CSRF::rotate();
        }
    }
}
