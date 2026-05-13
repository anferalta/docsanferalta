<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;

class AuditoriaAdminController extends BaseController {

    public function index() {
        $db = Conexao::getInstancia();

        // Filtros
        $filtros = [
            'utilizador' => $_GET['utilizador'] ?? '',
            'ip' => $_GET['ip'] ?? '',
            'acao' => $_GET['acao'] ?? '',
            'data_inicio' => $_GET['data_inicio'] ?? '',
            'data_fim' => $_GET['data_fim'] ?? ''
        ];

        // Ordenação
        $ordem = $_GET['ordem'] ?? 'criado_em';
        $dir = strtoupper($_GET['dir'] ?? 'DESC');

        if (!in_array($dir, ['ASC', 'DESC'])) {
            $dir = 'DESC';
        }

        // Paginação
        $pagina = max(1, intval($_GET['p'] ?? 1));
        $porPagina = 20;
        $offset = ($pagina - 1) * $porPagina;

        // Query unificada
        $sql = "
            SELECT 
                a.id,
                a.utilizador_id,
                u.nome AS utilizador_nome,
                a.acao,
                a.ip,
                a.detalhes,
                a.criado_em,
                'auditoria' AS origem
            FROM auditoria a
            LEFT JOIN utilizadores u ON u.id = a.utilizador_id

            UNION ALL

            SELECT
                e.id,
                e.apagado_por AS utilizador_id,
                u2.nome AS utilizador_nome,
                CONCAT('Eliminou ', e.tabela) AS acao,
                e.ip,
                e.dados AS detalhes,
                e.criado_em,
                'eliminacao' AS origem
            FROM auditoria_eliminacoes e
            LEFT JOIN utilizadores u2 ON u2.id = e.apagado_por
        ";

        // Transformar em subquery para aplicar filtros
        $sql = "SELECT * FROM ($sql) AS logs WHERE 1=1";

        // Filtros
        if ($filtros['utilizador']) {
            $sql .= " AND utilizador_nome LIKE " . $db->quote('%' . $filtros['utilizador'] . '%');
        }

        if ($filtros['ip']) {
            $sql .= " AND ip LIKE " . $db->quote('%' . $filtros['ip'] . '%');
        }

        if ($filtros['acao']) {
            $sql .= " AND acao LIKE " . $db->quote('%' . $filtros['acao'] . '%');
        }

        if ($filtros['data_inicio']) {
            $sql .= " AND DATE(criado_em) >= " . $db->quote($filtros['data_inicio']);
        }

        if ($filtros['data_fim']) {
            $sql .= " AND DATE(criado_em) <= " . $db->quote($filtros['data_fim']);
        }

        // Ordenação
        $sql .= " ORDER BY $ordem $dir";

        // Paginação
        $sql .= " LIMIT $porPagina OFFSET $offset";

        $logs = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        // Total
        $total = $db->query("SELECT COUNT(*) FROM ($sql) AS total")->fetchColumn();
        $paginas = ceil($total / $porPagina);

        $this->render('@admin/auditoria/index.twig', [
            'logs' => $logs,
            'filtros' => $filtros,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'ordem' => $ordem,
            'dir' => $dir
        ]);
    }

    public function detalhes($id) {
        $db = Conexao::getInstancia();

        // Buscar registo da auditoria normal
        $sql1 = "SELECT 
                a.id,
                a.utilizador_id,
                u.nome AS utilizador_nome,
                a.acao,
                a.ip,
                a.detalhes,
                a.criado_em,
                'auditoria' AS origem
            FROM auditoria a
            LEFT JOIN utilizadores u ON u.id = a.utilizador_id
            WHERE a.id = :id";

        $stmt = $db->prepare($sql1);
        $stmt->execute(['id' => $id]);
        $log = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Se não encontrou, tentar auditoria_eliminacoes
        if (!$log) {
            $sql2 = "SELECT 
                    e.id,
                    e.apagado_por AS utilizador_id,
                    u2.nome AS utilizador_nome,
                    CONCAT('Eliminou ', e.tabela) AS acao,
                    e.ip,
                    e.dados AS detalhes,
                    e.criado_em,
                    'eliminacao' AS origem
                FROM auditoria_eliminacoes e
                LEFT JOIN utilizadores u2 ON u2.id = e.apagado_por
                WHERE e.id = :id";

            $stmt = $db->prepare($sql2);
            $stmt->execute(['id' => $id]);
            $log = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$log) {
            Sessao::flash('erro', 'Registo não encontrado.');
            Helpers::redirect('/admin/auditoria');
        }

        return $this->render('@admin/auditoria/detalhes.twig', [
                    'log' => $log
        ]);
    }
}
