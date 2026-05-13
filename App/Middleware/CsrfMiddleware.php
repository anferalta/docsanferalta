<?php

namespace App\Middleware;

use App\Core\CSRF;

class CsrfMiddleware
{
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (!CSRF::validateFromRequest()) {
                http_response_code(419);
                die('Sessão expirada ou token CSRF inválido.');
            }
        }
    }
}