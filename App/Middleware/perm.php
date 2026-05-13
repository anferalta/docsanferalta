<?php
use App\Core\Permission;
use App\Core\Sessao;

return function($codigo) {
    if (!Permission::tem($codigo)) {
        Sessao::setFlash("Não tem permissão para aceder a esta página", "danger");
        header("Location: /403");
        exit;
    }
};