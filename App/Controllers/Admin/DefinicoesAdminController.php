<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BaseController;

class DefinicoesAdminController extends BaseController
{
    public function index(): void
    {
        $user = Auth::user();

        if (!$user) {
            $this->redirect('/login');
        }

        $isAdmin = $user->isAdmin();

        $this->render('@admin/definicoes/index.twig', [
            'podeGerirAreas' => $isAdmin || $user->hasPermissao('admin.tramitacao.areas.ver'),
            'podeGerirTipos' => $isAdmin || $user->hasPermissao('admin.documento-tipos.ver'),
            'podeGerirPerfis' => $isAdmin || $user->hasPermissao('admin.perfis.ver'),
            'podeGerirPermissoes' => $isAdmin || $user->hasPermissao('admin.permissoes.ver'),
            'podeGerirEstados' => $isAdmin,
        ]);
    }
}
