<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;

class TramitacaoDashboardAdminController extends BaseController
{
    public function index()
    {
        $db = Conexao::getInstancia();

        // ESTADOS REAIS DA BASE DE DADOS
        $novos               = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'novo'")->fetchColumn();
        $pendentes           = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'")->fetchColumn();
        $em_analise          = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'analise'")->fetchColumn();
        $em_tramitacao       = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'")->fetchColumn();
        $concluidos          = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'")->fetchColumn();
        $arquivados          = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'")->fetchColumn();

        return $this->render('@admin/tramitacao/dashboard.twig', [
            // VARIÁVEIS USADAS NO TOTAL
            'novos'               => $novos,
            'pendentes'           => $pendentes,
            'em_analise'          => $em_analise,
            'em_tramitacao'       => $em_tramitacao,
            'concluidos'          => $concluidos,
            'arquivados'          => $arquivados,

            // VARIÁVEIS USADAS NOS CARDS E GRÁFICOS
            'totalDocsPendentes'  => $pendentes,
            'totalDocsAnalise'    => $em_analise,
            'totalDocsTramitacao' => $em_tramitacao,
            'totalDocsConcluidos' => $concluidos,
            'totalDocsArquivados' => $arquivados,
        ]);
    }
}
