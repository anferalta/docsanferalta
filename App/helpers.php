<?php

use App\Models\Permissao;

function can(string $codigo): bool
{
    if (!isset($_SESSION['utilizador_id'])) {
        return false;
    }

    return Permissao::userHasPermission($_SESSION['utilizador_id'], $codigo);
}