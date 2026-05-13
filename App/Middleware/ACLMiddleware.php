<?php

namespace App\Middleware;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\Redirect;
use App\Core\AuditLogger;

class AclMiddleware
{
    private string $permission;

    public function __construct(string $permission = '')
    {
        $this->permission = $permission;
    }

    public function handle(): bool
    {
        // Se a rota não exige permissão, permitir
        if ($this->permission === '') {
            return true;
        }

        // Verificar permissão
        if (!Acl::can($this->permission)) {

            // Auditoria automática
            AuditLogger::log(
                'acesso_negado',
                "Permissão necessária: {$this->permission}"
            );

            // Redirecionar para página 403
            Redirect::to('/403');
            return false;
        }

        return true;
    }
}