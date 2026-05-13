<?php

namespace App\Middleware;

use App\Models\Permissao;
use App\Models\Auditoria;

class AcessoMiddleware
{
    public static function verificar(string $codigoPermissao)
    {
        // Utilizador autenticado?
        if (!isset($_SESSION['utilizador_id'])) {
            header("Location: /admin/login");
            exit;
        }

        $userId = $_SESSION['utilizador_id'];

        // Verificar permissão
        if (!Permissao::userHasPermission($userId, $codigoPermissao)) {

            // Registar tentativa negada
            Auditoria::registar(
                "Acesso negado à permissão: $codigoPermissao",
                $userId
            );

            // Página de acesso negado
            header("HTTP/1.1 403 Forbidden");
            echo "<h1>Acesso Negado</h1><p>Não tem permissão para aceder a esta área.</p>";
            exit;
        }
    }
}