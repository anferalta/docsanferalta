<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Models\Utilizador;
use App\Models\Documento;
use App\Models\Perfil;

class PainelController extends BaseController
{
    private Utilizador $userModel;
    private Documento $docModel;
    private Perfil $perfilModel;

    public function __construct()
    {
        parent::__construct();

        $this->userModel   = new Utilizador();
        $this->docModel    = new Documento();
        $this->perfilModel = new Perfil();
    }

    public function index(): void
    {
        $user = Auth::user();

        $totalUtilizadores = $this->userModel->count();
        $totalDocumentos   = $this->docModel->count();
        $totalPerfis       = $this->perfilModel->count();

        $this->view('painel/dashboard', [
            'titulo'             => 'Dashboard',
            'total_utilizadores' => $totalUtilizadores,
            'total_documentos'   => $totalDocumentos,
            'total_perfis'       => $totalPerfis
        ]);
    }
}