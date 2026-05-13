<?php

use App\Core\Middleware;
use App\Middleware\AcessoMiddleware;

// Middleware de autenticação
Middleware::register('auth', function () {
    if (!isset($_SESSION['utilizador_id'])) {
        header("Location: /admin/login");
        exit;
    }
});

// Middleware de permissões (ACL)
Middleware::register('perm', function ($codigoPermissao) {
    AcessoMiddleware::verificar($codigoPermissao);
});