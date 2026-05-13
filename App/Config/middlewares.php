<?php

use App\Core\Middleware;
use App\Core\Auth;
use App\Models\Auditoria;
use App\Core\CSRF;

Middleware::register('csrf', function () {
    CSRF::middleware();
});

Middleware::register('csrf', function () {
    CSRF::middleware();
    if (!Auth::check()) {
        // Não autenticado → redirecionar para login
        header('Location: /login');
        exit;
    }

    $user = Auth::user();

    // Utilizador inativo ou sem perfil válido
    if (!$user || $user->estado == 0 || $user->ativo == 0) {
        header('HTTP/1.1 403 Forbidden');
        echo "Acesso negado.";
        exit;
    }
});

Middleware::register('perm', function (string $codigo) {
    if (!Auth::check()) {
        header('Location: /login');
        exit;
    }

    $user = Auth::user();

    // Admin tem sempre acesso
    if ($user->isAdmin()) {
        return;
    }

    // Suporte a múltiplas permissões separadas por vírgula:
    // ex: perm:admin.utilizadores.ver,admin.perfis.ver
    $codigos = array_map('trim', explode(',', $codigo));

    $tem = false;
    foreach ($codigos as $c) {
        if ($user->hasPermissao($c)) {
            $tem = true;
            break;
        }
    }

    if (!$tem) {
        // Registar na auditoria tentativa de acesso negado
        Auditoria::registar(
            'ACESSO_NEGADO',
            $user->id,
            "Tentativa de acesso sem permissão: {$codigo}"
        );

        header('HTTP/1.1 403 Forbidden');
        echo "Acesso negado.";
        exit;
    }
});