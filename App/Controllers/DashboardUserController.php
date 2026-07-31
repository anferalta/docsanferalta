<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Conexao;

class DashboardUserController extends BaseController
{
    public function index()
    {
        $user = \App\Core\Auth::user();
        $db = Conexao::getInstancia();

        // Se for admin → redireciona para o dashboard admin
        if ($user->isAdmin()) {
            return $this->redirect('@site/admin/dashboard');
        }

        // Buscar documentos do utilizador
        $stmt = $db->prepare("
            SELECT estado_atual, COUNT(*) total
            FROM documentos
            WHERE criado_por = :uid
            GROUP BY estado_atual
        ");
        $stmt->execute(['uid' => $user->id]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Contadores
        $total        = 0;
        $novo         = 0;
        $pendentes    = 0;
        $analise      = 0;
        $emTramitacao = 0;
        $concluidos   = 0;
        $arquivados   = 0;
        $devolvidos   = 0;

        foreach ($rows as $r) {

            $estado = $r['estado_atual'];   // valores reais da BD
            $qtd    = (int) $r['total'];

            // Conta sempre para o total
            $total += $qtd;

            // Conta estado por estado (EXATAMENTE como vem da BD)
            switch ($estado) {

                case 'novo':
                    $novo = $qtd;
                    break;

                case 'pendente':
                    $pendentes = $qtd;
                    break;

                case 'analise':
                    $analise = $qtd;
                    break;

                case 'em_tramitacao':
                    $emTramitacao = $qtd;
                    break;

                case 'concluido':
                    $concluidos = $qtd;
                    break;

                case 'arquivado':
                    $arquivados = $qtd;
                    break;

                case 'devolvido':
                    $devolvidos = $qtd;
                    break;
            }
        }

        return $this->render('@site/dashboard/index.twig', [
            'total'         => $total,
            'novo'          => $novo,
            'pendentes'     => $pendentes,
            'analise'       => $analise,
            'emTramitacao'  => $emTramitacao,
            'concluidos'    => $concluidos,
            'arquivados'    => $arquivados,
            'devolvidos'    => $devolvidos,
        ]);
    }
}
