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

        // =========================
        // CONTAGENS PRINCIPAIS
        // =========================
        $totalUtilizadores = Utilizador::count();
        $totalDocumentos = Documento::count();
        $totalPerfis = class_exists(Perfil::class) ? Perfil::count() : 0;
        $totalPermissoes = class_exists(Permissao::class) ? Permissao::count() : 0;

        // =========================
        // CONTAGENS POR ESTADO
        // =========================
        $totalAtivos = (int) $db->query("
            SELECT COUNT(*) FROM utilizadores WHERE ativo = 1
        ")->fetchColumn();

        $totalPendentes = (int) $db->query("
            SELECT COUNT(*) FROM utilizadores 
            WHERE ativo = 0 AND aprovado_em IS NULL
        ")->fetchColumn();

        $totalBloqueados = (int) $db->query("
            SELECT COUNT(*) FROM utilizadores 
            WHERE ativo = 0 AND aprovado_em IS NOT NULL
        ")->fetchColumn();

        // =========================
        // ÚLTIMOS DOCUMENTOS
        // =========================
        $stmtDocs = $db->query("
            SELECT id, titulo, criado_em
            FROM documentos
            ORDER BY criado_em DESC
            LIMIT 10
        ");
        $ultimosDocs = $stmtDocs->fetchAll(\PDO::FETCH_ASSOC);

        $sql = "
            SELECT d.*, 
           t.nome AS tipo_nome,
           a.nome AS area_nome
            FROM documentos d
            LEFT JOIN documento_tipos t ON t.tipo_id = d.tipo_id
            LEFT JOIN documento_areas a ON a.id = d.area_atual_id
           ORDER BY d.id DESC
        ";

        $documentos = $db->query($sql)->fetchAll();

        // =========================
        // ÚLTIMOS UTILIZADORES
        // =========================
        $stmtUsers = $db->query("
            SELECT id, nome, email, criado_em
            FROM utilizadores
            ORDER BY criado_em DESC
            LIMIT 10
        ");
        $ultimosUsers = $stmtUsers->fetchAll(\PDO::FETCH_ASSOC);

        // =========================
        // REGISTOS POR MÊS (12 MESES)
        // =========================
        $stmtMeses = $db->query("
            SELECT 
                DATE_FORMAT(criado_em, '%Y-%m') AS ano_mes,
                COUNT(*) AS total
            FROM utilizadores
            WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
            GROUP BY ano_mes
            ORDER BY ano_mes ASC
        ");
        $rowsMeses = $stmtMeses->fetchAll(\PDO::FETCH_ASSOC);

        // Normalizar para 12 meses (meses sem registos = 0)
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

        // =========================
        // ÚLTIMOS LOGS / ATIVIDADE
        // =========================
        $ultimosLogs = Auditoria::ultimos(10);

        return $this->render('admin/dashboard/index.twig', [
                    'totalUtilizadores' => $totalUtilizadores,
                    'totalDocumentos' => $totalDocumentos,
                    'totalPerfis' => $totalPerfis,
                    'totalPermissoes' => $totalPermissoes,
                    'totalAtivos' => $totalAtivos,
                    'totalPendentes' => $totalPendentes,
                    'totalBloqueados' => $totalBloqueados,
                    'ultimosDocs' => $ultimosDocs,
                    'ultimosUsers' => $ultimosUsers,
                    'meses' => $meses,
                    'registosPorMes' => $registosPorMes,
                    'ultimosLogs' => $ultimosLogs,
        ]);
    }
}
