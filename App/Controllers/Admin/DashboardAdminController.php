<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Models\Utilizador;
use App\Models\Documento;
use App\Models\Auditoria;
use App\Models\Perfil;
use App\Models\Permissao;

class DashboardAdminController extends BaseController {

    public function index() {

        // ACL
        $this->authorize('admin.dashboard.ver');

        $db = Conexao::getInstancia();
        $user = \App\Core\Auth::user();

        // ============================================================
        // SE FOR ADMIN → CARREGA TODAS AS ESTATÍSTICAS
        // ============================================================
        if ($user->isAdmin()) {

            // UTILIZADORES
            $totalUtilizadores = Utilizador::count();
            $totalAtivos = (int) $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 1")->fetchColumn();
            $totalPendentes = (int) $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 0 AND aprovado_em IS NULL")->fetchColumn();
            $totalBloqueados = (int) $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 0 AND aprovado_em IS NOT NULL")->fetchColumn();

            // DOCUMENTOS
            $totalDocumentos = Documento::count();
            $totalDocsPendentes = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'")->fetchColumn();
            $totalDocsTramitacao = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'")->fetchColumn();
            $totalDocsAnalise = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'analise'")->fetchColumn();
            $totalDocsConcluidos = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'")->fetchColumn();
            $totalDocsArquivados = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'")->fetchColumn();
            $totalDocsDevolvidos = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'devolvido'")->fetchColumn();

            // ÚLTIMOS DOCUMENTOS
            $ultimosDocs = $db->query("
                SELECT id, titulo, criado_em
                FROM documentos
                ORDER BY criado_em DESC
                LIMIT 10
            ")->fetchAll(\PDO::FETCH_ASSOC);

            // ÚLTIMOS UTILIZADORES
            $ultimosUsers = $db->query("
                SELECT id, nome, email, criado_em
                FROM utilizadores
                ORDER BY criado_em DESC
                LIMIT 10
            ")->fetchAll(\PDO::FETCH_ASSOC);

            // GRÁFICO DE REGISTOS
            $stmtMeses = $db->query("
                SELECT DATE_FORMAT(criado_em, '%Y-%m') AS ano_mes, COUNT(*) AS total
                FROM utilizadores
                WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
                GROUP BY ano_mes
                ORDER BY ano_mes ASC
            ");
            $rowsMeses = $stmtMeses->fetchAll(\PDO::FETCH_ASSOC);

            $meses = [];
            $registosPorMes = [];

            $periodo = new \DatePeriod(
                (new \DateTime('first day of -11 month'))->setTime(0, 0),
                new \DateInterval('P1M'),
                (new \DateTime('first day of next month'))->setTime(0, 0)
            );

            $map = [];
            foreach ($rowsMeses as $r) {
                $map[$r['ano_mes']] = (int) $r['total'];
            }

            foreach ($periodo as $dt) {
                $key = $dt->format('Y-m');
                $meses[] = $dt->format('m/Y');
                $registosPorMes[] = $map[$key] ?? 0;
            }

            // LOGS
            $ultimosLogs = Auditoria::ultimos(10);

            return $this->render('admin/dashboard/index.twig', [
                'isAdmin' => true,

                // UTILIZADORES
                'totalUtilizadores' => $totalUtilizadores,
                'totalAtivos' => $totalAtivos,
                'totalPendentes' => $totalPendentes,
                'totalBloqueados' => $totalBloqueados,

                // DOCUMENTOS
                'totalDocumentos' => $totalDocumentos,
                'totalDocsPendentes' => $totalDocsPendentes,
                'totalDocsTramitacao' => $totalDocsTramitacao,
                'totalDocsAnalise' => $totalDocsAnalise,
                'totalDocsConcluidos' => $totalDocsConcluidos,
                'totalDocsArquivados' => $totalDocsArquivados,
                'totalDocsDevolvidos' => $totalDocsDevolvidos,

                // LISTAS
                'ultimosDocs' => $ultimosDocs,
                'ultimosUsers' => $ultimosUsers,

                // GRÁFICOS
                'meses' => $meses,
                'registosPorMes' => $registosPorMes,

                // LOGS
                'ultimosLogs' => $ultimosLogs,
            ]);
        }

        // ============================================================
        // UTILIZADOR NORMAL → APENAS OS DOCUMENTOS DELE
        // ============================================================

        $userId = $user->id;

        $stmt = $db->prepare("
            SELECT estado_atual, COUNT(*) total
            FROM documentos
            WHERE criado_por = :uid
            GROUP BY estado_atual
        ");
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalDocsDoUtilizador = 0;
        $docsUserPendentes = 0;
        $docsUserConcluidos = 0;

        foreach ($rows as $r) {
            $totalDocsDoUtilizador += (int) $r['total'];

            if ($r['estado_atual'] === 'pendente') {
                $docsUserPendentes = (int) $r['total'];
            }

            if ($r['estado_atual'] === 'concluido') {
                $docsUserConcluidos = (int) $r['total'];
            }
        }

        return $this->render('admin/dashboard/index.twig', [
            'isAdmin' => false,

            // DOCUMENTOS DO UTILIZADOR
            'totalDocsDoUtilizador' => $totalDocsDoUtilizador,
            'docsUserPendentes' => $docsUserPendentes,
            'docsUserConcluidos' => $docsUserConcluidos,
        ]);
    }
}
