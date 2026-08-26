<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Models\Utilizador;
use App\Models\Documento;
use App\Models\Auditoria;

class DashboardAdminController extends BaseController
{

    public function index()
    {
        // ACL
        $this->authorize('admin.dashboard.ver');

        $db = Conexao::getInstancia();
        $user = \App\Core\Auth::user();

        // ============================================================
        // SLA — Documentos por estado de prazo
        // ============================================================

        $slaOk = 0;
        $slaAlerta = 0;
        $slaAtrasado = 0;

        // Agora inclui "concluido" porque ainda está em tramitação até ser arquivado
        $sqlSLA = "
            SELECT 
                d.id,
                d.area_atual_desde,
                a.prazo_resposta
            FROM documentos d
            LEFT JOIN documento_areas a ON a.id = d.area_atual_id
            WHERE d.estado_atual IN ('novo','pendente','analise','em_tramitacao','concluido')
        ";

        $docsSLA = $db->query($sqlSLA)->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($docsSLA as $doc) {

            if (!$doc['area_atual_desde'] || !$doc['prazo_resposta']) {
                continue;
            }

            $inicio = new \DateTime($doc['area_atual_desde']);
            $agora = new \DateTime();
            $dias = $inicio->diff($agora)->days;

            $prazo = (int) $doc['prazo_resposta'];

            if ($dias <= $prazo) {
                $slaOk++;
            } elseif ($dias <= $prazo + 2) {
                $slaAlerta++;
            } else {
                $slaAtrasado++;
            }
        }

        // ============================================================
        // SE FOR ADMIN → CARREGA TODAS AS ESTATÍSTICAS
        // ============================================================

        if ($user->isAdmin()) {

            // UTILIZADORES
            $totalUtilizadores = Utilizador::count();
            $totalAtivos = (int) $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 1")->fetchColumn();
            $totalPendentes = (int) $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 0 AND aprovado_em IS NULL")->fetchColumn();
            $totalBloqueados = (int) $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 0 AND aprovado_em IS NOT NULL")->fetchColumn();

            // DOCUMENTOS — atualizado com "concluido"
            $totalDocumentos = Documento::count();
            $totalDocsPendentes = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'")->fetchColumn();
            $totalDocsAnalise = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'analise'")->fetchColumn();
            $totalDocsTramitacao = (int) $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'")->fetchColumn();
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

            // ============================================================
            // RANKING DE ÁREAS POR SLA — atualizado com "concluido"
            // ============================================================

            $sqlRanking = "
                SELECT 
                    a.nome AS area,
                    COUNT(d.id) AS total,
                    SUM(CASE 
                            WHEN TIMESTAMPDIFF(DAY, d.area_atual_desde, NOW()) <= a.prazo_resposta 
                            THEN 1 ELSE 0 END
                    ) AS dentro_prazo,
                    SUM(CASE 
                            WHEN TIMESTAMPDIFF(DAY, d.area_atual_desde, NOW()) > a.prazo_resposta 
                            THEN 1 ELSE 0 END
                    ) AS atrasados
                FROM documentos d
                LEFT JOIN documento_areas a ON a.id = d.area_atual_id
                WHERE d.estado_atual IN ('novo','pendente','analise','em_tramitacao','concluido')
                GROUP BY a.id
                ORDER BY atrasados DESC
            ";

            $rankingAreas = $db->query($sqlRanking)->fetchAll(\PDO::FETCH_ASSOC);

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
                        'totalDocsAnalise' => $totalDocsAnalise,
                        'totalDocsTramitacao' => $totalDocsTramitacao,
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
                        // SLA
                        'slaOk' => $slaOk,
                        'slaAlerta' => $slaAlerta,
                        'slaAtrasado' => $slaAtrasado,
                        // RANKING
                        'rankingAreas' => $rankingAreas,
            ]);
        }

        // ============================================================
        // UTILIZADOR NORMAL
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
                    'totalDocsDoUtilizador' => $totalDocsDoUtilizador,
                    'docsUserPendentes' => $docsUserPendentes,
                    'docsUserConcluidos' => $docsUserConcluidos,
        ]);

        $integridadeBD = null;
        $integridadeFiles = null;

        if ($ultimoBD) {
            $pathBD = $this->resolverCaminho($backupsDB[0]['path']);
            $integridadeBD = (new \App\Services\BackupIntegrityService())->analisar($pathBD, 'bd');
        }

        if ($ultimoFiles) {
            $pathFiles = $this->resolverCaminho($backupsFiles[0]['path']);
            $integridadeFiles = (new \App\Services\BackupIntegrityService())->analisar($pathFiles, 'files');
        }
    }
}
