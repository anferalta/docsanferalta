<?php

namespace App\Core;

use App\Core\Conexao;

class Menu
{
    public function getMenu(): array
    {
        $db = Conexao::getInstancia();

        // ============================
        // CONTADORES DE UTILIZADORES
        // ============================
        $totalUsers = $db->query("SELECT COUNT(*) FROM utilizadores")->fetchColumn();
        $usersAtivos = $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 1")->fetchColumn();
        $usersPendentes = $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 0 AND aprovado_em IS NULL")->fetchColumn();
        $usersBloqueados = $db->query("SELECT COUNT(*) FROM utilizadores WHERE ativo = 0 AND aprovado_em IS NOT NULL")->fetchColumn();

        // ============================
        // CONTADORES DE DOCUMENTOS
        // ============================
        $docsTotal = $db->query("SELECT COUNT(*) FROM documentos")->fetchColumn();
        $docsPendentes = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'")->fetchColumn();
        $docsAnalise = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'analise'")->fetchColumn();
        $docsTram = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'")->fetchColumn();
        $docsConcl = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'")->fetchColumn();
        $docsArquiv = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'")->fetchColumn();
        $docsDevolvidos = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'devolvido'")->fetchColumn();

        return [

            // ============================
            // GERAL
            // ============================
            ['header' => 'GERAL'],
            [
                'titulo' => 'Dashboard',
                'icone' => 'bi-speedometer2',
                'url' => '/admin/dashboard',
                'permissao' => 'admin.dashboard.ver',
                'principal' => true
            ],

            // ============================
            // UTILIZADORES
            // ============================
            ['header' => 'UTILIZADORES'],
            [
                'titulo' => 'Utilizadores',
                'icone' => 'bi-people',
                'url' => '/admin/utilizadores',
                'permissao' => 'admin.utilizadores.ver',
                'badge' => $totalUsers,
                'principal' => true
            ],
            [
                'titulo' => 'Pendentes',
                'icone' => 'bi-hourglass-split',
                'url' => '/admin/utilizadores/pendentes',
                'permissao' => 'admin.utilizadores.aprovar',
                'badge' => $usersPendentes
            ],
            [
                'titulo' => 'Ativos',
                'icone' => 'bi-person-check',
                'url' => '/admin/utilizadores/ativos',
                'permissao' => 'admin.utilizadores.ver',
                'badge' => $usersAtivos
            ],
            [
                'titulo' => 'Bloqueados',
                'icone' => 'bi-person-x',
                'url' => '/admin/utilizadores/bloqueados',
                'permissao' => 'admin.utilizadores.bloquear',
                'badge' => $usersBloqueados
            ],
            [
                'titulo' => 'Criar Utilizador',
                'icone' => 'bi-person-plus',
                'url' => '/admin/utilizadores/criar',
                'permissao' => 'admin.utilizadores.criar'
            ],

            // ============================
            // DOCUMENTOS
            // ============================
            ['header' => 'DOCUMENTOS'],
            [
                'titulo' => 'Documentos',
                'icone' => 'bi-folder',
                'url' => '/admin/documentos',
                'permissao' => 'admin.documentos.ver',
                'badge' => $docsTotal,
                'principal' => true
            ],
            
            [
                'titulo' => 'Arquivados',
                'icone' => 'bi-archive',
                'url' => '/admin/documentos/arquivados',
                'permissao' => 'admin.documentos.arquivados.ver',
                'badge' => $docsArquiv
            ],
            
            // ============================
            // TRAMITAÇÃO
            // ============================
            ['header' => 'TRAMITAÇÃO'],
            [
                'titulo' => 'Dashboard de Tramitação',
                'icone' => 'bi-graph-up',
                'url' => '/admin/tramitacao/dashboard',
                'permissao' => 'admin.tramitacao.dashboard'
            ],
            [
                'titulo' => 'Tramitação',
                'icone' => 'bi-diagram-3',
                'url' => '/admin/tramitacao',
                'permissao' => 'admin.tramitacao.ver',
                'badge' => $docsTram,
                'principal' => true
            ],
            [
                'titulo' => 'Áreas de Tramitação',
                'icone' => 'bi-diagram-2',
                'url' => '/admin/documento-areas',
                'permissao' => 'admin.tramitacao.areas.ver'
            ],

            // ============================
            // SISTEMA
            // ============================
            ['header' => 'SISTEMA'],
            [
                'titulo' => 'Perfis',
                'icone' => 'bi-person-badge',
                'url' => '/admin/perfis',
                'permissao' => 'admin.perfis.ver'
            ],
            [
                'titulo' => 'Permissões',
                'icone' => 'bi-shield-lock',
                'url' => '/admin/permissoes',
                'permissao' => 'admin.permissoes.ver'
            ],
            [
                'titulo' => 'Auditoria',
                'icone' => 'bi-search',
                'url' => '/admin/auditoria',
                'permissao' => 'admin.auditoria.ver'
            ],
            [
                'titulo' => 'Log Sistema',
                'icone' => 'bi-terminal',
                'url' => '/admin/logs',
                'permissao' => 'admin.logs.ver'
            ],
            [
                'titulo' => 'Backups',
                'icone' => 'bi-hdd-stack',
                'url' => '/admin/backups',
                'permissao' => 'admin.backups.bd.ver'
            ],
        ];
    }

    public function filtrarMenu(array $menu): array
    {
        $uid = $_SESSION['user_id'] ?? null;
        if (!$uid)
            return [];

        $user = \App\Core\Auth::user();
        $resultado = [];

        foreach ($menu as $item) {

            if (isset($item['header'])) {
                $resultado[] = $item;
                continue;
            }

            if ($user && $user->isAdmin()) {
                $resultado[] = $item;
                continue;
            }

            if (!isset($item['permissao']))
                continue;

            if (!\App\Core\Permission::tem($item['permissao']))
                continue;

            $resultado[] = $item;
        }

        return $resultado;
    }
}
