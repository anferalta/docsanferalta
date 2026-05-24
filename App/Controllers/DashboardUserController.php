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
            return $this->redirect('/admin/dashboard');
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
        $total = 0;
        $pendentes = 0;
        $analise = 0;
        $concluidos = 0;
        $arquivados = 0;
        $devolvidos = 0;

        foreach ($rows as $r) {
            $total += (int) $r['total'];

            switch ($r['estado_atual']) {
                case 'pendente':   $pendentes = $r['total']; break;
                case 'analise':    $analise = $r['total']; break;
                case 'concluido':  $concluidos = $r['total']; break;
                case 'arquivado':  $arquivados = $r['total']; break;
                case 'devolvido':  $devolvidos = $r['total']; break;
            }
        }

        return $this->render('dashboard/index.twig', [
            'total' => $total,
            'pendentes' => $pendentes,
            'analise' => $analise,
            'concluidos' => $concluidos,
            'arquivados' => $arquivados,
            'devolvidos' => $devolvidos,
        ]);
    }
}
