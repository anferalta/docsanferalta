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
                $uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
                $sessaoTemToken = isset($_SESSION['_csrf']) && is_string($_SESSION['_csrf']);
                $pedidoTemToken = isset($_POST['_csrf']) || isset($_POST['csrf'])
                    || isset($_SERVER['HTTP_X_CSRF_TOKEN']);
                error_log(sprintf(
                    '[CSRF] Rejeitado %s %s; session=%s; session_token=%s; request_token=%s',
                    $method,
                    $uri,
                    substr(hash('sha256', session_id()), 0, 12),
                    $sessaoTemToken ? 'presente' : 'ausente',
                    $pedidoTemToken ? 'presente' : 'ausente'
                ));

                if ($uri === '/login') {
                    \App\Core\Sessao::flash('erro', 'A sessão do formulário expirou. Por favor, tente iniciar sessão novamente.');
                    header('Location: /login', true, 303);
                    exit;
                }

                http_response_code(419);
                die('Sessão expirada ou token CSRF inválido. Atualize a página e tente novamente.');
            }
        }
    }
}
