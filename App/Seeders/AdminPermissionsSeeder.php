<?php

namespace App\Seeders;

use App\Core\Database;

class AdminPermissionsSeeder {

    public static function run() {
        $permissoes = [
        'admin.documentos.ver',
        'admin.documentos.criar',
        'admin.documentos.editar',
        'admin.documentos.eliminar',
            
        'admin.tramitacao.ver',
        'admin.tramitacao.encaminhar',
        'admin.tramitacao.comentar',
        'admin.tramitacao.arquivar',
        'admin.tramitacao.areas.ver',
        'admin.tramitacao.areas.criar',

        'admin.tramitacao.areas.ver',
        'admin.tramitacao.areas.criar',
        'admin.tramitacao.areas.editar',
        'admin.tramitacao.areas.apagar',
            
        'admin.utilizadores.ver',
        'admin.utilizadores.criar',
        'admin.utilizadores.editar',
        'admin.utilizadores.eliminar',
            
        'admin.perfis.ver',
        'admin.perfis.criar',
        'admin.perfis.editar',
        'admin.perfis.eliminar',
        'admin.permissoes.ver',
        'admin.permissoes.editar',
        'admin.auditoria.ver',
        'admin.notificacoes.ver'
        ];

        foreach ($permissoes as $p) {
            Database::insert('permissoes', [
                'nome' => $p,
                'descricao' => ucfirst(str_replace('.', ' ', $p))
            ]);
        }

        // atribuir tudo ao perfil admin (id = 1)
        $ids = Database::select("SELECT id FROM permissoes");

        foreach ($ids as $row) {
            Database::insert('perfil_permissoes', [
                'perfil_id' => 1,
                'permissao_id' => $row->id
            ]);
        }
    }
}
