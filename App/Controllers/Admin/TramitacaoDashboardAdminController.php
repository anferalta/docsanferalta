<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;

class TramitacaoDashboardAdminController extends BaseController
{
    public function index()
    {
        $db = Conexao::getInstancia();

        $pendentes      = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'novo'")->fetchColumn();
        $em_tramitacao  = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'")->fetchColumn();
        $em_analise     = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_analise'")->fetchColumn();
        $concluidos     = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'")->fetchColumn();
        $arquivados     = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'")->fetchColumn();

        return $this->render('@admin/tramitacao/dashboard.twig', [
            'pendentes'     => $pendentes,
            'em_tramitacao' => $em_tramitacao,
            'em_analise'    => $em_analise,
            'concluidos'    => $concluidos,
            'arquivados'    => $arquivados,
        ]);
    }
}
