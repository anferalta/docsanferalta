<?php

namespace App\Core;

use App\Core\Conexao;

class Menu
{

    public function getMenu(): array
    {
        $db = Conexao::getInstancia();

        // ============================
        // CONTADORES
        // ============================
        $pendentes = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'")->fetchColumn();
        $analise   = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'analise'")->fetchColumn();
        $tram      = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'")->fetchColumn();
        $concl     = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'")->fetchColumn();
        $arquiv    = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'")->fetchColumn();

        return [

            // ============================
            // GERAL
            // ============================
            ['header' => 'GERAL'],

            [
                'titulo'     => 'Dashboard',
                'icone'      => 'bi-speedometer2',
                'url'        => '/admin/dashboard',
                'permissao'  => 'admin.dashboard.ver',
                'principal'  => true
            ],

            // ============================
            // UTILIZADORES
            // ============================
            ['header' => 'UTILIZADORES'],

            [
                'titulo'     => 'Utilizadores',
                'icone'      => 'bi-people',
                'url'        => '/admin/utilizadores',
                'permissao'  => 'admin.utilizadores.ver',
                'principal'  => true
            ],
            [
                'titulo'     => 'Pendentes',
                'icone'      => 'bi-hourglass-split',
                'url'        => '/admin/utilizadores/pendentes',
                'permissao'  => 'admin.utilizadores.aprovar',
                'badge'      => $_SESSION['pendentesCount'] ?? 0
            ],
            [
                'titulo'     => 'Ativos',
                'icone'      => 'bi-person-check',
                'url'        => '/admin/utilizadores/ativos',
                'permissao'  => 'admin.utilizadores.ver'
            ],
            [
                'titulo'     => 'Bloqueados',
                'icone'      => 'bi-person-x',
                'url'        => '/admin/utilizadores/bloqueados',
                'permissao'  => 'admin.utilizadores.bloquear'
            ],
            [
                'titulo'     => 'Criar Utilizador',
                'icone'      => 'bi-person-plus',
                'url'        => '/admin/utilizadores/criar',
                'permissao'  => 'admin.utilizadores.criar'
            ],

            // ============================
            // DOCUMENTOS
            // ============================
            ['header' => 'DOCUMENTOS'],

            [
                'titulo'     => 'Documentos',
                'icone'      => 'bi-folder',
                'url'        => '/admin/documentos',
                'permissao'  => 'admin.documentos.ver',
                'principal'  => true
            ],
            [
                'titulo'     => 'Carregar Documento',
                'icone'      => 'bi-upload',
                'url'        => '/admin/documentos/criar',
                'permissao'  => 'admin.documentos.criar'
            ],
            [
                'titulo'     => 'Tipos de Documento',
                'icone'      => 'bi-tags',
                'url'        => '/admin/documento-tipos',
                'permissao'  => 'admin.documento-tipos.ver'
            ],
            [
                'titulo'     => 'Arquivados',
                'icone'      => 'bi-archive',
                'url'        => '/admin/documentos/arquivados',
                'permissao'  => 'admin.documentos.arquivados.ver',
                'badge'      => $arquiv
            ],

            // ============================
            // TRAMITAÇÃO
            // ============================
            ['header' => 'TRAMITAÇÃO'],

            [
                'titulo'     => 'Dashboard de Tramitação',
                'icone'      => 'bi-graph-up',
                'url'        => '/admin/tramitacao/dashboard',
                'permissao'  => 'admin.tramitacao.dashboard'
            ],
            [
                'titulo'     => 'Tramitação',
                'icone'      => 'bi-diagram-3',
                'url'        => '/admin/tramitacao',
                'permissao'  => 'admin.tramitacao.ver',
                'badge'      => $tram,
                'principal'  => true
            ],
            [
                'titulo'     => 'Áreas de Tramitação',
                'icone'      => 'bi-diagram-2',
                'url'        => '/admin/tramitacao/areas',
                'permissao'  => 'admin.tramitacao.areas.ver'
            ],

            // ============================
            // SISTEMA
            // ============================
            ['header' => 'SISTEMA'],

            [
                'titulo'     => 'Perfis',
                'icone'      => 'bi-person-badge',
                'url'        => '/admin/perfis',
                'permissao'  => 'admin.perfis.ver'
            ],
            [
                'titulo'     => 'Permissões',
                'icone'      => 'bi-shield-lock',
                'url'        => '/admin/permissoes',
                'permissao'  => 'admin.permissoes.ver'
            ],
            [
                'titulo'     => 'Auditoria',
                'icone'      => 'bi-search',
                'url'        => '/admin/auditoria',
                'permissao'  => 'admin.auditoria.ver'
            ],
            [
                'titulo'     => 'Log Sistema',
                'icone'      => 'bi-terminal',
                'url'        => '/admin/logs',
                'permissao'  => 'admin.logs.ver'
            ],
            [
                'titulo'     => 'Backups',
                'icone'      => 'bi-hdd-stack',
                'url'        => '/admin/backups',
                'permissao'  => 'admin.backups.bd.ver'
            ],
        ];
    }

    /**
     * Filtrar menu por permissões
     */
    public function filtrarMenu(array $menu): array
    {
        $uid = $_SESSION['user_id'] ?? null;
        if (!$uid)
            return [];

        $user = \App\Core\Auth::user();
        $resultado = [];

        foreach ($menu as $item) {

            // Headers passam sempre
            if (isset($item['header'])) {
                $resultado[] = $item;
                continue;
            }

            // Admin vê tudo
            if ($user && $user->isAdmin()) {
                $resultado[] = $item;
                continue;
            }

            // Sem permissão → não mostra
            if (!isset($item['permissao']))
                continue;

            if (!\App\Core\Permission::tem($item['permissao']))
                continue;

            $resultado[] = $item;
        }

        return $resultado;
    }
}
