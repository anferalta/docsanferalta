<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Models\Utilizador;
use App\Models\Perfil;
use App\Models\Documento;
use App\Models\Permissao;
use App\Models\Auditoria;

class DashboardController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.dashboard.ver');

        // Estatísticas principais
        $totalUtilizadores = Utilizador::count();
        $totalPerfis       = Perfil::count();
        $totalDocumentos   = Documento::count();
        $totalPermissoes   = Permissao::count();

        // Últimos registos
        $ultimosLogs = Auditoria::query()
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get();

        $ultimosDocs = Documento::query()
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get();

        $ultimosUsers = Utilizador::query()
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get();

        return $this->render('admin/dashboard.twig', [
            'title'            => 'Dashboard',
            'user'             => Auth::user(),
            'totalUtilizadores'=> $totalUtilizadores,
            'totalPerfis'      => $totalPerfis,
            'totalDocumentos'  => $totalDocumentos,
            'totalPermissoes'  => $totalPermissoes,
            'ultimosLogs'      => $ultimosLogs,
            'ultimosDocs'      => $ultimosDocs,
            'ultimosUsers'     => $ultimosUsers
        ]);
    }
}